<?php
/**
 * DokuWiki Plugin etherpadlite (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Braun <michael-dev@fami-braun.de>
 */

declare(strict_types=1);

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';
require_once DOKU_PLUGIN.'etherpadlite/externals/etherpad-lite-client/etherpad-lite-client.php';

enum EpAccessMode: string {
    case WikiRead = 'wikiread';
    case WikiWrite = 'wikiwrite';
}

class action_plugin_etherpadlite_etherpadlite extends DokuWiki_Action_Plugin {
    private bool $initialized = false;

    private readonly string $domain;
    private readonly string $ep_url;
    private readonly EtherpadLiteClient $ep_instance;
    private readonly string $ep_group;
    private readonly string $ep_url_args;
    private readonly string $groupid;

    private string $client = '';
    private string $clientname = '';

    public function register(Doku_Event_Handler $controller): void {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_tpl_metaheader_output');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_logoutconvenience');
    }

    private function createEPInstance(): void {
        if ($this->initialized) {
            return;
        }

        $domain = trim((string) $this->getConf('etherpadlite_domain'));
        if ($domain === '') {
            $domain = (string) ($_SERVER['HTTP_HOST'] ?? '');
        }
        $this->domain = $domain;

        $this->ep_url = rtrim(trim((string) $this->getConf('etherpadlite_url')), '/');
        $epKey = trim((string) $this->getConf('etherpadlite_apikey'));
        $this->ep_instance = new EtherpadLiteClient($epKey, $this->ep_url.'/api');
        $this->ep_group = trim((string) $this->getConf('etherpadlite_group'));
        $this->ep_url_args = trim((string) $this->getConf('etherpadlite_urlargs'));
        $this->groupid = (string) ($this->ep_instance->createGroupIfNotExistsFor($this->ep_group)?->groupID ?? '');

        $this->initialized = true;
    }

    private function getPageID(): string {
        global $meta, $rev;
        assert(is_array($meta[$rev]));
        if ($this->ep_group !== '') {
            return $this->groupid.'$'.$meta[$rev]['pageid'];
        }
        return $meta[$rev]['pageid'];
    }

    private function generatePageId(): string {
        return bin2hex(random_bytes(16));
    }

    private function renameCurrentPage(): void {
        global $meta, $rev, $pageid;

        assert(is_array($meta[$rev]));
        $pageid = $this->getPageID();

        $text = (string) ($this->ep_instance->getText($pageid)?->text ?? '');

        $authorid = (string) ($this->ep_instance->createAuthorIfNotExistsFor($this->client, $this->clientname)?->authorID ?? '');

        $newpageid = $this->generatePageId();
        if ($this->ep_group !== '') {
            $this->ep_instance->createGroupPad($this->groupid, $newpageid, $text, $authorid);
        } else {
            $this->ep_instance->createPad($newpageid, $text, $authorid);
        }
        $this->ep_instance->deletePad($pageid);

        $meta[$rev]['pageid'] = $newpageid;
        $pageid = $this->getPageID();
    }

    public function handle_logoutconvenience(Doku_Event &$event, mixed $param): void {
        global $ACT;
        if ($ACT === 'logout' && isset($_SESSION['ep_sessionID'])) {
            $this->createEPInstance();
            if ($this->ep_group !== '') {
                $this->ep_instance->deleteSession($_SESSION['ep_sessionID']);
                unset($_SESSION['ep_sessionID']);
            }
        }
    }

    public function handle_ajax(Doku_Event &$event, mixed $param): void {
        if (class_exists('action_plugin_ipgroup')) {
            $plugin = new action_plugin_ipgroup();
            $plugin->start($event, $param);
        }

        $call = (string) $event->data;
        if (method_exists($this, "handle_ajax_$call")) {
            header('Content-Type: application/json');
            try {
                $ret = $this->handle_ajax_inner($call);
            } catch (Throwable $e) {
                error_log('etherpadlite: '.$e->getMessage()."\n".$e->getTraceAsString());
                $ret = ['error' => $this->getLang('Server-Fehler (Pad-Plugin).')];
            }
            print json_encode($ret);
            $event->preventDefault();
        }
    }

    private function handle_ajax_inner(string $call): ?array {
        global $ID, $REV, $INFO, $rev, $meta, $pageid, $USERINFO;
        $this->createEPInstance();

        $remoteUser = (string) ($_SERVER['REMOTE_USER'] ?? '');
        if ($remoteUser !== '') {
            $this->client = $remoteUser;
        } else {
            if (empty($_SESSION['ep_anon_client'])) {
                $_SESSION['ep_anon_client'] = 'anon:'.bin2hex(random_bytes(16));
            }
            $this->client = $_SESSION['ep_anon_client'];
        }

        $this->clientname = (string) ($USERINFO['name'] ?? '');
        if ($this->clientname === '') {
            $this->clientname = $this->client;
        }

        $ID = cleanID((string) ($_POST['id'] ?? ''));
        if ($ID === '') {
            return null;
        }
        if (auth_quickaclcheck($ID) < AUTH_READ) {
            return [
                'error' => $this->getLang('Permission denied'),
            ];
        }

        $REV = (int) ($_POST['rev'] ?? 0);
        $INFO = pageinfo();
        $rev = (int) (($INFO['currentrev'] === '') ? $INFO['lastmod'] : $INFO['currentrev']);
        if ($rev === 0) {
            return [
                'error' => $this->getLang('You need to create (save) the non-empty page first.'),
            ];
        }

        $meta = p_get_metadata($ID, 'etherpadlite', METADATA_DONT_RENDER);
        $oldmeta = $meta;
        if (!is_array($meta)) {
            $meta = [];
        }

        $pageid = isset($meta[$rev]) ? $this->getPageID() : null;

        $_POST['isSaveable'] = isset($_POST['isSaveable']) && $_POST['isSaveable'] === 'true';

        if (!isset($_POST['accessPassword'])) {
            $_POST['accessPassword'] = '';
        }

        if (isset($_POST['readOnly'])) {
            $_POST['readOnly'] = ($_POST['readOnly'] === 'true');
        }

        if (isset($meta[$rev]) && ($meta[$rev]['owner'] !== $this->client)) {
            # PAD exists and is not owned by us
            $canWrite = ((!isset($meta[$rev]['writepw']) || hash_equals((string) $meta[$rev]['writepw'], (string) $_POST['accessPassword']))
                        && $INFO['writable']);
            $canRead = ((($meta[$rev]['readMode'] === EpAccessMode::WikiRead->value) || $INFO['writable'])
                        && (!isset($meta[$rev]['readpw']) || hash_equals((string) $meta[$rev]['readpw'], (string) $_POST['accessPassword']))
                        ) || $canWrite;
        } else { # no such pad or pad alread owned by me
            $canWrite = $_POST['isSaveable'] && $INFO['writable'];
            $canRead = $INFO['writable'];
            $_POST['readOnly'] = !$canWrite;
        }

        # default to write-access request if pad not exists, otherwise prefer write-access over readonly-access
        if (!isset($_POST['readOnly'])) {
            $_POST['readOnly'] = $pageid !== null ? !$canWrite : false;
        }

        # the master editor is always editable
        $_POST['readOnly'] = $_POST['readOnly'] && !$_POST['isSaveable'];

        # check if pad is owned by somebody else than how can save it (wikilock)
        if (isset($meta[$rev]) && ($meta[$rev]['owner'] !== $this->client) && $_POST['isSaveable']) {
            return [
                'error' => sprintf($this->getLang('Permission denied - pad is owned by %s, who needs to lock (edit) the page.'), $meta[$rev]['owner']),
            ];
        }

        if ((!$canWrite) && (!$canRead || (!$_POST['readOnly']))) {
            return [
                'error' => $this->getLang('Permission denied'),
                'askPassword' => (isset($meta[$rev]['readpw']) || isset($meta[$rev]['writepw'])),
            ];
        }

        if ($_POST['isSaveable'] && checklock($ID)) {
            return [
                'error' => $this->getLang('Permission denied - page locked by somebody else'),
            ];
        }

        if ($_POST['isSaveable']) {
            lock($ID);
        }

        $ret = $this->{"handle_ajax_$call"}();
        if ($meta !== $oldmeta) {
            p_set_metadata($ID, ['etherpadlite' => $meta]);
        }
        return $ret;
    }

    private function getPageInfo(): array {
        global $rev, $meta, $pageid;

        $canPassword = $this->ep_group !== '' && $meta[$rev]['owner'] === $this->client;

        // 2021-02-02: disable password functionality as dropped from etherpad lite, see https://github.com/michael-dev/dokuwikietherpadlite/issues/22
        $ret = ['canPassword' => $canPassword];
        $ret['encAMode'] = $meta[$rev]['encAMode'];
        $ret['readMode'] = $meta[$rev]['readMode'];
        $ret['writeMode'] = EpAccessMode::WikiWrite->value;

        if (isset($meta[$rev]['readpw'])) {
            $ret['readpw'] = '***';
            $ret['readMode'] .= '+password';
        } else {
            $ret['readpw'] = '';
        }

        if (isset($meta[$rev]['writepw'])) {
            $ret['writepw'] = '***';
            $ret['writeMode'] .= '+password';
        } else {
            $ret['writepw'] = '';
        }

        $ret['name'] = (string) $pageid;

        if ($_POST['readOnly']) {
            $roid = (string) ($this->ep_instance->getReadOnlyID($pageid)?->readOnlyID ?? '');
            $ret['url'] = $this->ep_url.'/ro/'.$roid;
        } else {
            $ret['url'] = $this->ep_url.'/p/'.$pageid;
        }
        $ret['url'] .= '?'.$this->ep_url_args;

        $ret['isOwner'] = ($meta[$rev]['owner'] === $this->client);
        $ret['isReadonly'] = $_POST['readOnly'];

        return $ret;
    }

    public function handle_ajax_pad_security(): array {
        global $rev, $meta;

        if (!checkSecurityToken()) {
            return ['error' => $this->getLang('CSRF protection.')];
        }

        if (!is_array($meta) || !isset($meta[$rev])) {
            return ['error' => $this->getLang('Permission denied')];
        }

        if ($meta[$rev]['owner'] !== $this->client) {
            return ['error' => $this->getLang('Permission denied')];
        }

        $readModeRaw = (string) ($_POST['readMode'] ?? '');
        $writeModeRaw = (string) ($_POST['writeMode'] ?? '');

        $readMode = EpAccessMode::tryFrom(str_replace('+password', '', $readModeRaw));
        $encAMode = EpAccessMode::tryFrom((string) ($_POST['encAMode'] ?? ''));
        if ($readMode === null || $encAMode === null) {
            return ['error' => $this->getLang('Permission denied')];
        }

        if (!str_contains($readModeRaw, 'password')) {
            $_POST['readpw'] = '';
        }
        if (!str_contains($writeModeRaw, 'password')) {
            $_POST['writepw'] = '';
        }

        $this->renameCurrentPage();

        $password = (string) ($_POST['readpw'] ?? '');
        if ($password !== '***') {
            if ($password === '') {
                unset($meta[$rev]['readpw']);
            } else {
                $meta[$rev]['readpw'] = $password;
            }
        }

        $password = (string) ($_POST['writepw'] ?? '');
        if ($password !== '***') {
            if ($password === '') {
                unset($meta[$rev]['writepw']);
            } else {
                $meta[$rev]['writepw'] = $password;
            }
        }

        $meta[$rev]['encAMode'] = $encAMode->value;
        $meta[$rev]['readMode'] = $readMode->value;

        return $this->getPageInfo();
    }

    public function handle_ajax_pad_getText(): array {
        global $rev, $meta, $pageid;

        if (!is_array($meta) || !isset($meta[$rev])) {
            return ['error' => $this->getLang('Permission denied')];
        }

        $text = (string) ($this->ep_instance->getText($pageid)?->text ?? '');

        return [
            'status' => 'OK',
            'text' => $text,
        ];
    }

    public function handle_ajax_pad_close(): array {
        global $conf, $ID, $rev, $meta, $pageid;

        if (!checkSecurityToken()) {
            return ['error' => $this->getLang('CSRF protection.')];
        }

        if (!is_array($meta) || !isset($meta[$rev])) {
            return ['error' => $this->getLang('Permission denied')];
        }

        if ($meta[$rev]['owner'] !== $this->client) {
            return ['error' => $this->getLang('Permission denied')];
        }

        $text = (string) ($this->ep_instance->getText($pageid)?->text ?? '');

        # save as draft before deleting
        if ($conf['usedraft']) {
            $draft = [
                'id' => $ID,
                'prefix' => substr((string) ($_POST['prefix'] ?? ''), 0, -1),
                'text' => $text,
                'suffix' => $_POST['suffix'] ?? '',
                'date' => (int) ($_POST['date'] ?? 0),
                'client' => $this->client,
            ];
            $cname = getCacheName($draft['client'].$ID, '.draft');
            if (!io_saveFile($cname, serialize($draft))) {
                return ['error' => $this->getLang('pad could not be safed as draft')];
            }
        }
        $this->ep_instance->deletePad($pageid);

        unset($meta[$rev]);

        return [
            'status' => 'OK',
            'text' => $text,
        ];
    }

    public function handle_ajax_has_pad(): array {
        global $rev, $meta;

        return ['exists' => isset($meta[$rev])];
    }

    public function handle_ajax_pad_open(): array {
        global $ID, $rev, $meta, $pageid;

        if (!checkSecurityToken()) {
            return ['error' => $this->getLang('CSRF protection.')];
        }

        $authorid = (string) ($this->ep_instance->createAuthorIfNotExistsFor($this->client, $this->clientname)?->authorID ?? '');

        if ($this->ep_group !== '') {
            if (!isset($_SESSION['ep_sessionID'])) {
                $cookies = $this->ep_instance->createSession($this->groupid, $authorid, time() + 7 * 24 * 60 * 60);
                $_SESSION['ep_sessionID'] = (string) ($cookies?->sessionID ?? '');
            }
            $host = parse_url($this->ep_url, PHP_URL_HOST);

            $cookie_options = [
                'expires' => '0',
                'path' => '/',
                'domain' => $host,
                'secure' => true,
                'httponly' => false,
                'samesite' => 'None',
            ];
            $cookie_options['domain'] = $this->domain;
            setcookie('sessionID', $_SESSION['ep_sessionID'], $cookie_options);
        }

        if (!isset($meta[$rev])) {
            if (!$_POST['isSaveable'] || $_POST['readOnly']) {
                return ['error' => $this->getLang('There is no such pad.')];
            }

            /** new pad */
            if (isset($_POST['text'])) {
                $text = $_POST['text'];
            } else {
                $text = rawWiki($ID, $rev);
                if (!$text) {
                    $text = pageTemplate($ID);
                }
            }
            $pageid = $this->generatePageId();
            if ($this->ep_group !== '') {
                $this->ep_instance->createGroupPad($this->groupid, $pageid, $text, $authorid);
            } else {
                $this->ep_instance->createPad($pageid, $text, $authorid);
            }
            $meta[$rev] = [];
            $meta[$rev]['pageid'] = $pageid;
            $meta[$rev]['owner'] = $this->client;
            $meta[$rev]['encAMode'] = EpAccessMode::WikiWrite->value;
            $meta[$rev]['readMode'] = EpAccessMode::WikiWrite->value;
        } else {
            $pageid = $meta[$rev]['pageid'];
            /* in case pad is already deleted, recreate it. Should not happen, but this resolves this kind of conflict. */
            try {
                if ($this->ep_group !== '') {
                    $this->ep_instance->createGroupPad($this->groupid, $pageid, '', $authorid);
                } else {
                    $this->ep_instance->createPad($pageid, '', $authorid);
                }
            } catch (Throwable) {
            }
        }
        $pageid = $this->getPageID();

        $ret = $this->getPageInfo();
        $ret = array_merge($ret, [
            'sessionID' => $_SESSION['ep_sessionID'] ?? null,
            'domain' => $this->domain,
        ]);

        return $ret;
    }

    public function handle_tpl_metaheader_output(Doku_Event &$event, mixed $param): void {
        global $ACT, $INFO;
        $code = 'document.domain = "'.trim((string) $this->getConf('etherpadlite_domain')).'";';
        $this->include_script($event, $code);

        if (!in_array($ACT, ['edit', 'create', 'preview', 'locked', 'recover'], true)) {
            return;
        }
        $config = [
            'id' => $INFO['id'],
            'rev' => (($INFO['currentrev'] === '') ? $INFO['lastmod'] : $INFO['currentrev']),
            'base' => DOKU_BASE.'lib/plugins/etherpadlite/',
            'act' => $ACT,
        ];
        $path = 'scripts/etherpadlite.js';

        $this->include_script($event, 'var etherpad_lite_config = '.json_encode($config));
        $this->link_script($event, DOKU_BASE.'lib/plugins/etherpadlite/'.$path);
    }

    private function include_script(Doku_Event $event, string $code): void {
        $event->data['script'][] = [
            'type' => 'text/javascript',
            'charset' => 'utf-8',
            '_data' => $code,
        ];
    }

    private function link_script(Doku_Event $event, string $url): void {
        $event->data['script'][] = [
            'type' => 'text/javascript',
            'charset' => 'utf-8',
            'src' => $url,
            'defer' => true,
        ];
    }
}
