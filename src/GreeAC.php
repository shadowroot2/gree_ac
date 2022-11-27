<?php
    namespace Gree;

    use Exception;
    use InvalidArgumentException;

    /**
     * GREE AC API
     *
     * @version   v.1.0
     * @author    ShadoW
     * @copyright 2022
     */
    class GreeAC
    {
        private bool   $_debug     = false;
        private int    $_port      = 7000;
        private string $_host;
        private string $_cid;
        private string $_sec_key;
        private int    $_try_limit = 3;
        private array  $_alias     = [
            'Pow'        => [ 'off', 'on' ],
            'Mod'        => [ 'auto', 'cool', 'dry', 'fan', 'heat' ],
            'TemUn'      => [ 'celsius', 'fahrenheit' ],
            'WdSpd'      => [ 'auto', 'low', 'medium-low', 'medium', 'medium-high', 'high' ],
            'Air'        => [ 'off', 'on' ],
            'Blo'        => [ 'off', 'on' ],
            'Health'     => [ 'off', 'on' ],
            'SwhSlp'     => [ 'off', 'on' ],
            'Lig'        => [ 'off', 'on' ],
            'SwingLfRig' => [ 'default', 'full swing', 'pos 1', 'pos 2', 'pos 3', 'pos 4', 'pos 5' ],
            'SwUpDn'     => [
                'default',
                'full swing',
                'upmost position',
                'middle-up position',
                'middle position',
                'middle-low position',
                'lowest position',
                'downmost region',
                'middle-low region',
                'middle region',
                'middle-up region',
                'upmost region'
            ],
            'Quiet'      => [ 'off', 'on' ],
            'Tur'        => [ 'off', 'on' ],
            'SvSt'       => [ 'off', 'on' ],
            'StHt'       => [ 'off', 'on' ]
        ];

        /**
         * Constructor
         *
         * @param $host
         * @param $cid
         * @param $sec_key
         *
         * @throws \Exception
         */
        final function __construct($host = '192.168.1.1', $cid = '', $sec_key = 'a3K8Bx%2r8Y7#xDh')
        {
            $this->setHost($host);
            if(!empty($cid))
                $this->setCID($cid);
            $this->_sec_key = $sec_key;
        }

        /**
         * Set debug
         *
         * @param bool $debug
         *
         * @return void
         */
        final function setDebug(bool $debug) : void
        {
            $this->_debug = $debug;
        }

        /**
         * Set host IP
         *
         * @param string $host
         *
         * @return void
         */
        final function setHost(string $host) : void
        {
            if(!filter_var($host, FILTER_VALIDATE_IP))
                throw new InvalidArgumentException(__METHOD__ . ': Incorrect IP format');

            $this->_host = $host;
        }

        /**
         * Set device CID (MAC)
         *
         * @param string $cid
         *
         * @return void
         * @throws \Exception
         */
        final function setCID(string $cid) : void
        {
            if(!filter_var($cid, FILTER_VALIDATE_REGEXP, [ 'options' => [ 'regexp' => '/^[0-9a-z]{12}$/i' ] ]))
                throw new Exception(__METHOD__ . ': Incorrect cid format');

            $this->_cid = $cid;
        }

        /**
         * Set secure key
         *
         * @param string $sec_key
         *
         * @return void
         * @throws \Exception
         */
        final function setSecKey(string $sec_key) : void
        {
            if(!filter_var($sec_key, FILTER_VALIDATE_REGEXP, [ 'options' => [ 'regexp' => '/^[0-9a-zA-Z]{16}$/' ] ]))
                throw new Exception(__METHOD__ . ': Incorrect key format');

            $this->_sec_key = $sec_key;
        }

        /**
         * Search Gree AC on $this->_host
         *
         * @return array
         * @throws \Exception
         */
        final function scan() : array
        {
            # Send request
            $response = $this->sendRequest([ 't' => 'scan' ], false);

            # Есть ответ?
            if(!isset($response['pack']))
                throw new Exception('No GreeAC found :(', 404);

            # Setting CID
            if(isset($response['pack']['mac']))
                $this->setCID($response['pack']['mac']);

            return $response['pack'];
        }

        /**
         * Getting bind (secure) key
         *
         * @return string
         * @throws \Exception
         */
        final function getBindKey() : string
        {
            # Send request
            $response = $this->sendRequest([
                'cid'  => '',
                'i'    => 1,
                't'    => 'pack',
                'uid'  => 0,
                'tcid' => $this->_cid,
                'pack' => [
                    't'   => 'bind',
                    'mac' => $this->_cid,
                    'uid' => 0
                ]
            ], false);

            # Check response value
            if(!isset($response['pack']['key']))
                throw new Exception(__METHOD__ . ': Can not get sec-key from response: ' . print_r($response, true));

            # Remember the key
            $this->setSecKey($response['pack']['key']);

            return $response['pack']['key'];
        }

        /**
         * Get current status values of AC
         *
         * @return array
         * @throws \Exception
         */
        final function status() : array
        {
            # Sending request
            $response = $this->sendRequest([
                'cols' => [
                    'Pow',
                    'Mod',
                    'SetTem',
                    'WdSpd',
                    'Air',
                    'Blo',
                    'Health',
                    'SwhSlp',
                    'Lig',
                    'SwingLfRig',
                    'SwUpDn',
                    'Quiet',
                    'Tur',
                    'StHt',
                    'TemUn',
                    'HeatCoolType',
                    'TemRec',
                    'SvSt'
                ],
                'mac'  => $this->_cid,
                't'    => 'status'
            ]);

            # Formatting status
            $status = [];
            if(isset($response['pack']['dat']))
            {
                foreach($response['pack']['cols'] as $k => $col)
                {
                    # Value from response
                    $value = $response['pack']['dat'][ $k ];

                    # Alias value
                    if(isset($this->_alias[ $col ]))
                        $value = $this->_alias[ $col ][ $value ];

                    # Adding to array
                    $status[ $col ] = $value;
                }
            }

            return $status;
        }

        /**
         * Get current Pow status
         *
         * @return array
         * @throws \Exception
         */
        final function getPower() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'    => 'status',
                'cols' => [ 'Pow' ],
                'mac'  => $this->_cid
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Pow value from response: ' . print_r($response, true));

            return [ 'Pow' => $this->_alias['Pow'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Switch ON AC
         *
         * @return array
         * @throws \Exception
         */
        final function on() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'Pow' ],
                'p'   => [ 1 ],
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Pow value from response: ' . print_r($response, true));

            return [ 'Pow' => $this->_alias['Pow'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Switch OFF AC
         *
         * @return array
         * @throws \Exception
         */
        final function off() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'Pow' ],
                'p'   => [ 0 ]
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Pow value from response: ' . print_r($response, true));

            return [ 'Pow' => $this->_alias['Pow'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Get current work-mode
         *
         * @return array
         * @throws \Exception
         */
        final function getMode() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'    => 'status',
                'cols' => [ 'Mod' ],
                'mac'  => $this->_cid
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Mod value from response: ' . print_r($response, true));

            return [ 'Mod' => $this->_alias['Mod'][ $response['pack']['dat'][0] ] ];
        }

        /**
         * Set work-mode
         *
         * @param string $mode
         *
         * @return array|string[]
         * @throws \Exception
         */
        final function setMode(string $mode) : array
        {
            # Numeric value if Mod
            $modes = array_flip($this->_alias['Mod']);
            if(!isset($modes[ $mode ]))
                throw new InvalidArgumentException(__METHOD__ . ': Incorrect mode value');

            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'Mod' ],
                'p'   => [ $modes[ $mode ] ]
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Mod value from response: ' . print_r($response, true));

            return [ 'Mod' => $this->_alias['Mod'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Get current vent speed
         *
         * @return array
         * @throws \Exception
         */
        final function getVentSpeed() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'    => 'status',
                'cols' => [ 'WdSpd' ],
                'mac'  => $this->_cid
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get WdSpd value from response: ' . print_r($response, true));

            return [ 'WdSpd' => $this->_alias['WdSpd'][ $response['pack']['dat'][0] ] ];
        }

        /**
         * Set vent speed
         *
         * @param string $speed
         *
         * @return array|string[]
         * @throws \Exception
         */
        final function setVentSpeed(string $speed) : array
        {
            # Numeric value of WdSpd
            $speeds = array_flip($this->_alias['WdSpd']);
            if(!isset($speeds[ $speed ]))
                throw new InvalidArgumentException(__METHOD__ . ': Incorrect speed value');

            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'WdSpd' ],
                'p'   => [ $speeds[ $speed ] ]
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get WdSpd value from response: ' . print_r($response, true));

            return [ 'WdSpd' => $this->_alias['WdSpd'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Get current target temperature
         *
         * @return array
         * @throws \Exception
         */
        final function getTemperature() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'    => 'status',
                'cols' => [ 'SetTem', 'Add0.5' ],
                'mac'  => $this->_cid
            ]);

            # Check response value
            if(!isset($response['pack']['dat'][0]))
                throw new Exception(__METHOD__ . ': Can not get SetTem value from response: ' . print_r($response, true));

            # Status
            $status = [ 'SetTem' => $response['pack']['dat'][0] ];
            if(isset($response['pack']['dat'][1]))
                $status['Add0.5'] = $response['pack']['dat'][1];

            return $status;
        }

        /**
         * Set temperature in celsius
         *
         * Note: If you need set with 0.5 step, use $add05 true or false
         *
         * @param int $temperature
         * @param bool $add05
         *
         * @return array
         * @throws \Exception
         */
        final function setTemperature(int $temperature, bool $add05 = false) : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'SetTem', 'Add0.5' ],
                'p'   => [ $temperature, ($add05 ? 1 : 0) ]
            ]);

            # Check response value
            if(!isset($response['pack']))
                throw new Exception(__METHOD__ . ': Can not get SetTem value from response: ' . print_r($response, true));

            return [ 'SetTem' => $response['pack']['val'][0] ];
        }

        /**
         * Status of Health function
         *
         * @return array
         * @throws \Exception
         */
        final function getHealth() : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'    => 'status',
                'cols' => [ 'Health' ],
                'mac'  => $this->_cid
            ]);

            # Check response value
            if(!isset($response['pack']['dat'][0]))
                throw new Exception(__METHOD__ . ': Can not get Health value from response: ' . print_r($response, true));

            return [ 'Health' => $this->_alias['Health'][ $response['pack']['dat'][0] ] ];
        }

        /*
         * Switch on/off Health function
         *
         * @param bool $switch
         *
         * @return array
         * @throws \Exception
         */
        final function setHealth(bool $switch) : array
        {
            # Sending request
            $response = $this->sendRequest([
                't'   => 'cmd',
                'opt' => [ 'Health' ],
                'p'   => [ ($switch ? 1 : 0) ],
            ]);

            # Check response value
            if(!isset($response['pack']['val'][0]))
                throw new Exception(__METHOD__ . ': Can not get Health value from response: ' . print_r($response, true));

            return [ 'Health' => $this->_alias['Health'][ $response['pack']['val'][0] ] ];
        }

        /**
         * Send request to Gree AC
         *
         * @param array $request
         * @param bool $preformat
         *
         * @return array
         * @throws \Exception
         */
        private function sendRequest(array $request, bool $preformat = true) : array
        {
            # Debug
            if($this->_debug)
                echo 'Request clear: ' . print_r($request, true) . PHP_EOL;

            # Preformat standard request
            if($preformat)
            {
                $request = [
                    't'    => 'pack',
                    'i'    => 0,
                    'uid'  => 0,
                    'cid'  => $this->_cid,
                    'tcid' => '',
                    'pack' => $request
                ];
            }

            # Encrypt pack if exists pack key
            if(isset($request['pack']) && is_array($request['pack']))
                $request['pack'] = $this->encrypt($request['pack']);

            # Final JSON request
            $json_request = json_encode($request, JSON_UNESCAPED_UNICODE);

            # Debug
            if($this->_debug)
                echo 'Request to ' . $this->_host . ':' . $this->_port . ' : ' . $json_request . PHP_EOL;

            # Socket connection open
            $fp = fsockopen('udp://' . $this->_host, $this->_port, $errno, $errstr);
            if(!$fp)
                throw new Exception('Can not connect to: ' . $this->_host . ':' . $this->_port . ', Error: ' . $errstr, $errno);

            # Try send
            for($i = 0; $i <= $this->_try_limit; $i++)
            {
                # Write request to socket
                fwrite($fp, $json_request);

                # Reading JSON response from socket
                $json_response = fread($fp, 1024);

                # Is it string?
                if(is_string($json_response))
                {
                    # Decrypting response
                    $response = json_decode($json_response, true);

                    # Socket connection close
                    fclose($fp);

                    # Decoding pack
                    if(isset($response['pack']) && is_string($response['pack']))
                        $response['pack'] = $this->decrypt($response['pack']);

                    # Debug
                    if($this->_debug)
                        echo 'Response: ' . print_r($response, true);

                    # Return response
                    return (array) $response;
                }
            }

            # Socket connection close
            fclose($fp);

            return [];
        }

        /**
         * Encryption
         *
         * @param array $data
         *
         * @return string
         */
        private function encrypt(array $data) : string
        {
            return base64_encode(openssl_encrypt(json_encode($data, JSON_UNESCAPED_UNICODE), 'aes-128-ecb', $this->_sec_key, OPENSSL_RAW_DATA));
        }

        /**
         * Decryption
         *
         * @param string $data
         *
         * @return array
         */
        private function decrypt(string $data) : array
        {
            return (array) json_decode(openssl_decrypt(base64_decode($data), 'aes-128-ecb', $this->_sec_key, OPENSSL_RAW_DATA), true);
        }
    }