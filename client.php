<?php

/**
 * KrmPesan PHP SDK.
 *
 * @version     3.0.0
 *
 * @see         https://github.com/KrmPesan/SDK-PHP
 *
 * @author      KrmPesan <support@krmpesan.com>
 * @copyright   2023 KrmPesan
 */

namespace KrmPesan;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * KrmPesan Client Class For Handle REST API Request.
 *
 * @see https://docs.krmpesan.com/
 */
class Client
{
    /**
     * Default Curl Timeout.
     *
     * @var int
     *
     * @see https://www.php.net/manual/en/function.curl-setopt
     */
    protected $timeout;

    /**
     * Default API Url.
     *
     * @var string
     */
    protected $apiUrl = 'https://api.krmpesan.app';

    /**
     * Default TimeZone For DateTime
     * Example: Asia/Jakarta.
     *
     * @var string
     */
    protected $timezone;

    /**
     * Store Token to File JSON Format.
     */
    protected $tokenFile;

    /**
     * API Token.
     *
     * @var string
     */
    protected $token;

    /**
     * Refresh Token.
     *
     * @var string
     */
    protected $refreshToken;

    /**
     * API Token.
     *
     * @var string
     */
    protected $deviceId;

    /**
     * Token Expired.
     *
     * @var string
     */
    protected $expiredAt;

    /**
     * Custom Request Header.
     *
     * @var array
     */
    protected $customHeader;

    /**
     * Construct Function.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->timezone = isset($data['timezone']) ? $data['timezone'] : 'Asia/Jakarta';

        // Set Token Path
        if (isset($data['tokenFile']) and !empty($data['tokenFile'])) {
            // check path directory is exist
            if (!is_dir($data['tokenFile'])) {
                throw new Exception('Directory not found.');
            }

            // save path
            $this->tokenFile = $data['tokenFile'].'/token.json';

            // load token
            $this->getToken();
        } else {
            // Set Token (optional)
            $this->token = $data['idToken'];

            // Set DeviceId
            if (!isset($data['deviceId']) or empty($data['deviceId'])) {
                throw new Exception('DeviceId is required.');
            } else {
                $this->deviceId = $data['deviceId'];
            }

            // Set Refresh Token
            if (!isset($data['refreshToken']) or empty($data['refreshToken'])) {
                throw new Exception('Token is required.');
            } else {
                $this->refreshToken = $data['refreshToken'];
            }
        }

        // Set Custom Header
        $this->customHeader = isset($data['headers']) ? $data['headers'] : null;

        // validate token
        $this->validateToken();
    }

    /**
     * Curl Post or Get Function.
     *
     * @param string $type
     * @param string $url
     * @param array  $form
     */
    private function action($type, $url, $form = null, $file = null)
    {
        // setup url
        $buildUrl = $this->apiUrl.'/'.$url;

        // set default header
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$this->token;

        // use custom header if not null
        if ($this->customHeader) {
            $headers = $this->customHeader;
        }

        // build curl instance
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $buildUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (isset($file)) {
            curl_setopt($ch, CURLOPT_INFILE, fopen($file, 'r'));
            curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
        } else {
            $headers[] = 'Content-Type: application/json';
        }

        if (isset($form)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        }

        // check action type
        if ($type == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($type == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        } elseif ($type == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        // running curl
        $result = curl_exec($ch);

        // throw error
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        // stop connection
        curl_close($ch);

        // return result request
        return $result;
    }

    public function request($type, $url, $form = null)
    {
        try {
            $this->validateToken();

            return $this->action($type, $url, $form);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Refresh Token.
     *
     * @return void
     */
    public function refreshToken()
    {
        $url = 'tokens?refresh_token='.$this->refreshToken.'&device_key='.$this->deviceId;
        $response = $this->action('GET', $url);
        $data = json_decode($response, true);
        $this->token = $data['IdToken'];

        // load token to internal function
        $this->storeToken();

        return $data;
    }

    public function getToken()
    {
        if (isset($this->tokenFile) and !empty($this->tokenFile)) {
            $getFile = file_get_contents($this->tokenFile);
            $parseFile = json_decode($getFile, true);
            $refreshToken = isset($parseFile['refreshToken']) ? $parseFile['refreshToken'] : null;
            $deviceId = isset($parseFile['deviceId']) ? $parseFile['deviceId'] : null;
            $idToken = isset($parseFile['idToken']) ? $parseFile['idToken'] : null;
            $expiredAt = isset($parseFile['expiredAt']) ? $parseFile['expiredAt'] : null;

            if (!$refreshToken) {
                throw new Exception('refreshToken Not Found at '.$this->tokenFile);
            }

            // set refresh token
            $this->refreshToken = $refreshToken;

            if (!$deviceId) {
                throw new Exception('deviceId Not Found at '.$this->tokenFile);
            }

            // set refresh token
            $this->deviceId = $deviceId;

            if (!$idToken xor !$expiredAt) {
                $this->refreshToken();
            } else {
                $this->token = $idToken;
                $this->expiredAt = $expiredAt;
            }
        }

        $result = array(
            'idToken'      => $this->token,
            'refreshToken' => $this->refreshToken,
            'expiredAt'    => $this->expiredAt,
        );

        return $result;
    }

    public function storeToken()
    {
        if (isset($this->tokenFile) and !empty($this->tokenFile)) {
            try {
                $getFile = file_get_contents($this->tokenFile);
                $parseFile = json_decode($getFile, true);

                $date = new DateTime('now', new DateTimeZone($this->timezone));
                $date->modify('+1 day');
                $parseFile['idToken'] = $this->token;
                $parseFile['expiredAt'] = $date->format('Y-m-d H:i:s');

                file_put_contents($this->tokenFile, json_encode($parseFile));
            } catch (Exception $e) {
                throw new Exception('tokenFile Error.!');
            }
        }
    }

    public function validateToken()
    {
        try {
            $date = new DateTime('now', new DateTimeZone($this->timezone));
            $now = $date->format('Y-m-d H:i:s');

            if ($now > $this->expiredAt) {
                $this->refreshToken();
            }

            // success
        } catch (Exception $e) {
            // error
            print_r($e);
        }
    }

    /**
     * Create Template Message.
     *
     * @param string $name
     * @param string $category    UTILLITY, AUTHENTICATION, MARKETING
     * @param string $description
     */
    public function createTemplate($name, $category, $description)
    {
        // build form
        $form = json_encode(array(
            'name'        => $name,
            'category'    => $category,
            'description' => $description,
        ));

        return $this->request('POST', 'messages/template', $form);
    }

    /**
     * Create Template Language.
     *
     * @param string $slug
     * @param string $lang    en, id
     * @param string $message
     * @param array  $fields  ["fields-1", "fields-2", "fields-3"]
     * @param array  $header  [
     *                        "type" => "image|document",
     *                        "url" => "https://example.com/image.png"
     *                        ]
     * @param string $footer
     * @param array  $button
     */
    public function createTemplateLang($slug, $lang, $message, $fields, $header = null, $footer = null, $button = null)
    {
        // build form
        $form = json_encode(array(
            'slug'     => $slug,
            'language' => $lang,
            'message'  => $message,
            'fields'   => $fields,
            'header'   => isset($header) ? $header : null,
            'footer'   => isset($footer) ? $footer : null,
            'button'   => isset($button) ? $button : null,
        ));

        return $this->request('POST', 'messages/template/lang', $form);
    }

    /**
     * Send Message Text.
     *
     * @param string|int $to
     * @param string     $templateLanguage
     * @param string     $templateName
     * @param array      $body
     *
     * @return void
     */
    public function sendMessageTemplateText($to, $templateName, $templateLanguage, $body)
    {
        // build form
        $form = json_encode(array(
            'phone'             => $to,
            'template_name'     => $templateName,
            'template_language' => $templateLanguage,
            'template'          => (object) array(
                'body' => $body,
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Message Image.
     *
     * @param string|int $to
     * @param string     $templateLanguage
     * @param string     $templateName
     * @param array      $body
     * @param string     $image
     *
     * @return void
     */
    public function sendMessageTemplateImage($to, $templateName, $templateLanguage, $body, $image)
    {
        // build form
        $form = json_encode(array(
            'phone'             => $to,
            'template_name'     => $templateName,
            'template_language' => $templateLanguage,
            'template'          => (object) array(
                'body'   => $body,
                'header' => array(
                    'type' => 'image',
                    'url'  => $image,
                ),
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Message Document.
     *
     * @param string|int $to
     * @param string     $templateLanguage
     * @param string     $templateName
     * @param array      $body
     * @param string     $document
     *
     * @return void
     */
    public function sendMessageTemplateDocument($to, $templateName, $templateLanguage, $body, $document)
    {
        // build form
        $form = json_encode(array(
            'phone'             => $to,
            'template_name'     => $templateName,
            'template_language' => $templateLanguage,
            'template'          => (object) array(
                'body'   => $body,
                'header' => array(
                    'type' => 'document',
                    'url'  => $document,
                ),
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Message Button.
     *
     * @param string|int $to
     * @param string     $templateLanguage
     * @param string     $templateName
     * @param array      $body
     * @param string     $button
     *
     * @return void
     */
    public function sendMessageTemplateButton($to, $templateName, $templateLanguage, $body, $button)
    {
        // build form
        $form = json_encode(array(
            'phone'             => $to,
            'template_name'     => $templateName,
            'template_language' => $templateLanguage,
            'template'          => (object) array(
                'body'    => $body,
                'buttons' => array(
                    'url'  => $button,
                ),
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Reply Text.
     *
     * @param string|int $to
     * @param string     $text
     *
     * @return void
     */
    public function sendReplyText($to, $text)
    {
        // build form
        $form = json_encode(array(
            'phone' => $to,
            'reply' => (object) array(
                'type' => 'text',
                'text' => $text,
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Reply Image.
     *
     * @param string|int $to
     * @param string     $image
     * @param string     $caption
     *
     * @return void
     */
    public function sendReplyImage($to, $image, $caption = '')
    {
        // build form
        $form = json_encode(array(
            'phone' => $to,
            'reply' => (object) array(
                'type'    => 'image',
                'image'   => $image,
                'caption' => $caption,
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Send Reply document.
     *
     * @param string|int $to
     * @param string     $document
     *
     * @return void
     */
    public function sendReplyDocument($to, $document)
    {
        // build form
        $form = json_encode(array(
            'phone' => $to,
            'reply' => (object) array(
                'type'       => 'image',
                'document'   => $document,
            ),
        ));

        return $this->request('POST', 'messages', $form);
    }

    /**
     * Get Device Data.
     */
    public function getDevice()
    {
        return $this->request('GET', 'devices');
    }

    /**
     * Get All Messages.
     */
    public function getMessages()
    {
        return $this->request('GET', 'messages');
    }

    public function upload($fileData)
    {
        // parse file and get file information
        // https://www.php.net/manual/en/function.realpath
        $file = realpath($fileData);
        // https://www.php.net/manual/en/function.basename
        $filename = basename($file);
        // https://www.php.net/manual/en/function.mime-content-type
        $filemime = mime_content_type($file);

        $presign = $this->request('POST', 'files/generate', json_encode(array(
            'filename' => $filename,
            'mime'     => $filemime,
            'expired'  => 30,
        )));

        $resp = json_decode($presign, true);

        if (!isset($resp['data']) and !isset($resp['data']['url'])) {
            throw new Exception('Failed to generate presign url');
        }

        $url = $resp['data']['url'];
        $urlClean = explode('?', $url)[0];

        // Create a cURL handle
        $curl = curl_init();

        // Set the cURL options
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_PUT, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_INFILE, fopen($fileData, 'r'));
        curl_setopt($curl, CURLOPT_INFILESIZE, filesize($fileData));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: $filemime"));

        // Execute the cURL request
        $response = curl_exec($curl);

        // Check if the request was successful
        if ($response === false) {
            throw  new Exception('Error uploading file: '.curl_error($curl));
        }

        // Close the cURL handle
        curl_close($curl);

        return $urlClean;
    }
}