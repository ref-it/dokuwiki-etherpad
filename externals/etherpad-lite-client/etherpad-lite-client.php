<?php
class EtherpadLiteClient {

    const API_VERSION             = "1";

    const CODE_OK                 = 0;
    const CODE_INVALID_PARAMETERS = 1;
    const CODE_INTERNAL_ERROR     = 2;
    const CODE_INVALID_FUNCTION   = 3;
    const CODE_INVALID_API_KEY    = 4;

    protected $apiKey = "";
    protected $baseUrl = "http://localhost:9001/api";
  
    public function __construct($apiKey, $baseUrl = null) {
        $this->apiKey  = $apiKey;
        if (isset($baseUrl)) {
            $this->baseUrl = $baseUrl;
        }
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("[{$this->baseUrl}] is not a valid URL");
        }
    }

    protected function get($function, array $arguments = []) {
        return $this->call($function, $arguments, 'GET');
    }

    protected function post($function, array $arguments = []) {
        return $this->call($function, $arguments, 'POST');
    }

    protected function call($function, array $arguments = [], $method = 'GET') {
        $arguments['apikey'] = $this->apiKey;
        $url = $this->baseUrl."/".self::API_VERSION."/".$function;

        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_TIMEOUT, 20);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($arguments));
        $result = curl_exec($c);
        //file_put_contents('logs.txt', $result.PHP_EOL , FILE_APPEND | LOCK_EX);
        $result = json_decode($result);
        //file_put_contents('logs.txt', $result.PHP_EOL , FILE_APPEND | LOCK_EX);
        curl_close($c);
        
        if(!$result) {
            throw new UnexpectedValueException("Empty or No Response from the server");
        }
        
        // $result = json_decode($result);
        if ($result === null) {
            throw new UnexpectedValueException("JSON response could not be decoded");
        }

        return $this->handleResult($result);
    }

    protected function handleResult($result) {
        //var_dump($result);
        if (!isset($result->code)) {
            throw new RuntimeException("API response has no code");
        }
        if (!isset($result->message)) {
            throw new RuntimeException("API response has no message");
        }
        if (!isset($result->data)) {
            $result->data = null;
        }

        switch ($result->code) {
            case self::CODE_OK:
                return $result->data;
            case self::CODE_INVALID_PARAMETERS:
            case self::CODE_INVALID_API_KEY:
                throw new InvalidArgumentException($result->message);
            case self::CODE_INTERNAL_ERROR:
                throw new RuntimeException($result->message);
            case self::CODE_INVALID_FUNCTION:
                throw new BadFunctionCallException($result->message);
            default:
                throw new RuntimeException("An unexpected error occurred whilst handling the response");
        }
    }

    // GROUPS
    // Pads can belong to a group. There will always be public pads that doesnt belong to a group (or we give this group the id 0)
    
    // creates a new group 
    public function createGroup() {
        return $this->post("createGroup");
    }

    // this functions helps you to map your application group ids to etherpad lite group ids 
    public function createGroupIfNotExistsFor($groupMapper) {
        return $this->post("createGroupIfNotExistsFor", [
            "groupMapper" => $groupMapper
        ]);
    }

    // deletes a group 
    public function deleteGroup($groupID) {
        return $this->post("deleteGroup", [
            "groupID" => $groupID
        ]);
    }

    // returns all pads of this group
    public function listPads($groupID) {
        return $this->get("listPads", [
            "groupID" => $groupID
        ]);
    }

    // creates a new pad in this group 
    public function createGroupPad($groupID, $padName, $text) {
        return $this->post("createGroupPad", [
            "groupID" => $groupID,
            "padName" => $padName,
            "text"    => $text
        ]);
    }

    // AUTHORS
    // Theses authors are bind to the attributes the users choose (color and name). 

    // creates a new author 
    public function createAuthor($name) {
        return $this->post("createAuthor", [
            "name" => $name
        ]);
    }

    // this functions helps you to map your application author ids to etherpad lite author ids 
    public function createAuthorIfNotExistsFor($authorMapper, $name) {
        return $this->post("createAuthorIfNotExistsFor", [
            "authorMapper" => $authorMapper,
            "name"         => $name
        ]);
    }

    // SESSIONS
    // Sessions can be created between a group and a author. This allows
    // an author to access more than one group. The sessionID will be set as
    // a cookie to the client and is valid until a certian date.

    // creates a new session 
    public function createSession($groupID, $authorID, $validUntil) {
        return $this->post("createSession", [
            "groupID"    => $groupID,
            "authorID"   => $authorID,
            "validUntil" => $validUntil
        ]);
    }

    // deletes a session 
    public function deleteSession($sessionID) {
        return $this->post("deleteSession", [
            "sessionID" => $sessionID
        ]);
    }

    // returns informations about a session 
    public function getSessionInfo($sessionID) {
        return $this->get("getSessionInfo", [
            "sessionID" => $sessionID
        ]);
    }

    // returns all sessions of a group 
    public function listSessionsOfGroup($groupID) {
        return $this->get("listSessionsOfGroup", [
            "groupID" => $groupID
        ]);
    }

    // returns all sessions of an author 
    public function listSessionsOfAuthor($authorID) {
        return $this->get("listSessionsOfAuthor", [
            "authorID" => $authorID
        ]);
    }

    // PAD CONTENT
    // Pad content can be updated and retrieved through the API

    // returns the text of a pad 
    public function getText($padID, $rev=null) {
        $params = ["padID" => $padID];
        if (isset($rev)) {
            $params["rev"] = $rev;
        }
        return $this->get("getText", $params);
    }

    // returns the text of a pad as html
    public function getHTML($padID, $rev=null) {
        $params = ["padID" => $padID];
        if (isset($rev)) {
            $params["rev"] = $rev;
        }
        return $this->get("getHTML", $params);
    }

    // sets the text of a pad 
    public function setText($padID, $text) {
        return $this->post("setText", [
            "padID" => $padID, 
            "text"  => $text
        ]);
    }

    // sets the html text of a pad 
    public function setHTML($padID, $html) {
        return $this->post("setHTML", [
            "padID" => $padID, 
            "html"  => $html
        ]);
    }

    // PAD
    // Group pads are normal pads, but with the name schema
    // GROUPID$PADNAME. A security manager controls access of them and its
    // forbidden for normal pads to include a $ in the name.

    // creates a new pad
    public function createPad($padID, $text) {
        return $this->post("createPad", [
            "padID" => $padID, 
            "text"  => $text
        ], 'POST');
    }

    // returns the number of revisions of this pad 
    public function getRevisionsCount($padID) {
        return $this->get("getRevisionsCount", [
            "padID" => $padID
        ]);
    }

    // deletes a pad 
    public function deletePad($padID) {
        return $this->post("deletePad", [
            "padID" => $padID
        ]);
    }

    // returns the read only link of a pad 
    public function getReadOnlyID($padID) {
        return $this->get("getReadOnlyID", [
            "padID" => $padID
        ]);
    }

    // sets a boolean for the public status of a pad 
    public function setPublicStatus($padID, $publicStatus) {
        if (is_bool($publicStatus)) {
        $publicStatus = $publicStatus ? "true" : "false";
        }
        return $this->post("setPublicStatus", [
            "padID"        => $padID,
            "publicStatus" => $publicStatus
        ]);
    }

    // return true of false 
    public function getPublicStatus($padID) {
        return $this->get("getPublicStatus", [
            "padID" => $padID
        ]);
    }

    // returns ok or a error message 
    public function setPassword($padID, $password) {
        return $this->post("setPassword", [
            "padID"    => $padID,
            "password" => $password
        ]);
    }

    // returns true or false 
    public function isPasswordProtected($padID) {
        return $this->get("isPasswordProtected", [
            "padID" => $padID
        ]);
    }
}

