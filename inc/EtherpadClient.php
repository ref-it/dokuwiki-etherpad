<?php
/**
 * Minimal Etherpad HTTP API client.
 *
 * Implements only the operations the etherpadlite plugin actually needs.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

class EtherpadClient {
    private const API_VERSION = '1.3.1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('API key must not be empty');
        }
    }

    public function createGroupIfNotExistsFor(string $groupMapper): ?object {
        return $this->call('createGroupIfNotExistsFor', ['groupMapper' => $groupMapper], 'POST');
    }

    public function createAuthorIfNotExistsFor(string $authorMapper, ?string $name = null): ?object {
        $params = ['authorMapper' => $authorMapper];
        if ($name !== null) {
            $params['name'] = $name;
        }
        return $this->call('createAuthorIfNotExistsFor', $params, 'POST');
    }

    public function createPad(string $padID, ?string $text = null, ?string $authorId = null): ?object {
        $params = ['padID' => $padID];
        if ($text !== null) {
            $params['text'] = $text;
        }
        if ($authorId !== null) {
            $params['authorId'] = $authorId;
        }
        return $this->call('createPad', $params, 'POST');
    }

    public function createGroupPad(string $groupID, string $padName, ?string $text = null, ?string $authorId = null): ?object {
        $params = ['groupID' => $groupID, 'padName' => $padName];
        if ($text !== null) {
            $params['text'] = $text;
        }
        if ($authorId !== null) {
            $params['authorId'] = $authorId;
        }
        return $this->call('createGroupPad', $params, 'POST');
    }

    public function deletePad(string $padID): ?object {
        return $this->call('deletePad', ['padID' => $padID], 'POST');
    }

    public function deleteSession(string $sessionID): ?object {
        return $this->call('deleteSession', ['sessionID' => $sessionID], 'POST');
    }

    public function createSession(string $groupID, string $authorID, int $validUntil): ?object {
        return $this->call('createSession', [
            'groupID' => $groupID,
            'authorID' => $authorID,
            'validUntil' => $validUntil,
        ], 'POST');
    }

    public function getText(string $padID, ?int $rev = null): ?object {
        $params = ['padID' => $padID];
        if ($rev !== null) {
            $params['rev'] = $rev;
        }
        return $this->call('getText', $params, 'GET');
    }

    public function getReadOnlyID(string $padID): ?object {
        return $this->call('getReadOnlyID', ['padID' => $padID], 'GET');
    }

    private function call(string $function, array $params, string $method): ?object {
        $params['apikey'] = $this->apiKey;
        $url = $this->baseUrl.'/'.self::API_VERSION.'/'.$function;

        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_TIMEOUT, 20);

        if ($method === 'POST') {
            curl_setopt($c, CURLOPT_URL, $url);
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            // Explicit '&' separator: DokuWiki changes the arg_separator.output
            // ini setting globally (inc/init.php, for safe HTML embedding),
            // which would otherwise corrupt this query string for the wire -
            // the apikey parameter would be absorbed into the previous
            // parameter's value instead of staying separate.
            curl_setopt($c, CURLOPT_URL, $url.'?'.http_build_query($params, '', '&'));
        }

        $response = curl_exec($c);

        if ($response === false) {
            $error = curl_error($c);
            curl_close($c);
            throw new RuntimeException("cURL request failed: {$error}");
        }
        curl_close($c);

        if ($response === '') {
            throw new UnexpectedValueException('Empty response from the Etherpad API');
        }

        $result = json_decode($response);
        if ($result === null) {
            throw new UnexpectedValueException("Could not decode Etherpad API response: {$response}");
        }

        return $this->handleResult($result);
    }

    private function handleResult(object $result): ?object {
        if (!isset($result->code) || !isset($result->message)) {
            throw new RuntimeException('Malformed Etherpad API response');
        }
        if ($result->code !== 0) {
            throw new RuntimeException($result->message);
        }
        return $result->data ?? null;
    }
}
