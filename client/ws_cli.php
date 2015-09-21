<?php
/**
 * @author will <wizarot@gmail.com>
 * @link http://wizarot.me/
 *
 * Date: 15/9/21
 * Time: 下午1:55
 */

//php 的websocket客户端
class Client
{
    protected $socket, $is_connected = FALSE, $is_closing = FALSE, $last_opcode = NULL,
        $close_status = NULL, $huge_payload = NULL;
    protected static          $opcodes      = array(
        'continuation' => 0,
        'text'         => 1,
        'binary'       => 2,
        'close'        => 8,
        'ping'         => 9,
        'pong'         => 10,
    );
    protected                 $socket_uri;

    /**
     * @param string $uri A ws/wss-URI
     * @param array  $options
     *   Associative array containing:
     *   - timeout:      Set the socket timeout in seconds.  Default: 5
     *   - headers:      Associative array of headers to set/override.
     */
    public function __construct( $uri, $options = array() )
    {
        $this->options = $options;
        if ( !array_key_exists( 'timeout', $this->options ) ) $this->options[ 'timeout' ] = 5;
        // the fragment size
        if ( !array_key_exists( 'fragment_size', $this->options ) ) $this->options[ 'fragment_size' ] = 4096;
        $this->socket_uri = $uri;
    }

    public function __destruct()
    {
        if ( $this->socket ) {
            if ( get_resource_type( $this->socket ) === 'stream' ) fclose( $this->socket );
            $this->socket = NULL;
        }
    }

    /**
     * Perform WebSocket handshake
     */
    protected function connect()
    {
        $url_parts = parse_url( $this->socket_uri );
        $scheme = $url_parts[ 'scheme' ];
        $host = $url_parts[ 'host' ];
        $user = isset( $url_parts[ 'user' ] ) ? $url_parts[ 'user' ] : '';
        $pass = isset( $url_parts[ 'pass' ] ) ? $url_parts[ 'pass' ] : '';
        $port = isset( $url_parts[ 'port' ] ) ? $url_parts[ 'port' ] : ( $scheme === 'wss' ? 443 : 80 );
        $path = isset( $url_parts[ 'path' ] ) ? $url_parts[ 'path' ] : '/';
        $query = isset( $url_parts[ 'query' ] ) ? $url_parts[ 'query' ] : '';
        $fragment = isset( $url_parts[ 'fragment' ] ) ? $url_parts[ 'fragment' ] : '';
        $path_with_query = $path;
        if ( !empty( $query ) ) $path_with_query .= '?' . $query;
        if ( !empty( $fragment ) ) $path_with_query .= '#' . $fragment;
        if ( !in_array( $scheme, array( 'ws', 'wss' ) ) ) {
            throw new Exception(
                "Url should have scheme ws or wss, not '$scheme' from URI '$this->socket_uri' ."
            );
        }
        $host_uri = ( $scheme === 'wss' ? 'ssl' : 'tcp' ) . '://' . $host;
        // Open the socket.  @ is there to supress warning that we will catch in check below instead.
        $this->socket = @fsockopen( $host_uri, $port, $errno, $errstr, $this->options[ 'timeout' ] );
        if ( $this->socket === FALSE ) {
            throw new Exception(
                "Could not open socket to \"$host:$port\": $errstr ($errno)."
            );
        }
        // Set timeout on the stream as well.
        stream_set_timeout( $this->socket, $this->options[ 'timeout' ] );
        // Generate the WebSocket key.
        $key = self::generateKey();
        // Default headers (using lowercase for simpler array_merge below).
        $headers = array(
            'host'                  => $host . ":" . $port,
            'user-agent'            => 'websocket-client-php',
            'connection'            => 'Upgrade',
            'upgrade'               => 'websocket',
            'sec-websocket-key'     => $key,
            'sec-websocket-version' => '13',
        );
        // Handle basic authentication.
        if ( $user || $pass ) {
            $headers[ 'authorization' ] = 'Basic ' . base64_encode( $user . ':' . $pass ) . "\r\n";
        }
        // Deprecated way of adding origin (use headers instead).
        if ( isset( $this->options[ 'origin' ] ) ) $headers[ 'origin' ] = $this->options[ 'origin' ];
        // Add and override with headers from options.
        if ( isset( $this->options[ 'headers' ] ) ) {
            $headers = array_merge( $headers, array_change_key_case( $this->options[ 'headers' ] ) );
        }
        $header =
            "GET " . $path_with_query . " HTTP/1.1\r\n"
            . implode(
                "\r\n", array_map(
                          function ( $key, $value ) {
                              return "$key: $value";
                          }, array_keys( $headers ), $headers
                      )
            )
            . "\r\n\r\n";
        // Send headers.
        $this->write( $header );
        // Get server response.
        $response = '';
        do {
            $buffer = stream_get_line( $this->socket, 1024, "\r\n" );
            $response .= $buffer . "\n";
            $metadata = stream_get_meta_data( $this->socket );
        } while ( !feof( $this->socket ) && $metadata[ 'unread_bytes' ] > 0 );
        /// @todo Handle version switching
        // Validate response.
        if ( !preg_match( '#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches ) ) {
            $address = $scheme . '://' . $host . $path_with_query;
            throw new Exception(
                "Connection to '{$address}' failed: Server sent invalid upgrade response:\n"
                . $response
            );
        }
        $keyAccept = trim( $matches[ 1 ] );
        $expectedResonse = base64_encode( pack( 'H*', sha1( $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11' ) ) );
        if ( $keyAccept !== $expectedResonse ) {
            throw new Exception( 'Server sent bad upgrade response.' );
        }
        $this->is_connected = TRUE;
    }

    /**
     * Generate a random string for WebSocket key.
     * @return string Random string
     */
    protected static function generateKey()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
        $key = '';
        $chars_length = strlen( $chars );
        for ( $i = 0; $i < 16; $i++ ) $key .= $chars[ mt_rand( 0, $chars_length - 1 ) ];

        return base64_encode( $key );
    }


    public function send( $payload, $opcode = 'text', $masked = TRUE )
    {
        if ( !$this->is_connected ) $this->connect(); /// @todo This is a client function, fixme!
        if ( !in_array( $opcode, array_keys( self::$opcodes ) ) ) {
            throw new Exception( "Bad opcode '$opcode'.  Try 'text' or 'binary'." );
        }
        // record the length of the payload
        $payload_length = strlen( $payload );
        $fragment_cursor = 0;
        // while we have data to send
        while ( $payload_length > $fragment_cursor ) {
            // get a fragment of the payload
            $sub_payload = substr( $payload, $fragment_cursor, $this->options[ 'fragment_size' ] );
            // advance the cursor
            $fragment_cursor += $this->options[ 'fragment_size' ];
            // is this the final fragment to send?
            $final = $payload_length <= $fragment_cursor;
            // send the fragment
            $this->send_fragment( $final, $sub_payload, $opcode, $masked );
            // all fragments after the first will be marked a continuation
            $opcode = 'continuation';
        }
    }

    protected function send_fragment( $final, $payload, $opcode, $masked )
    {
        // Binary string for header.
        $frame_head_binstr = '';
        // Write FIN, final fragment bit.
        $frame_head_binstr .= (bool)$final ? '1' : '0';
        // RSV 1, 2, & 3 false and unused.
        $frame_head_binstr .= '000';
        // Opcode rest of the byte.
        $frame_head_binstr .= sprintf( '%04b', self::$opcodes[ $opcode ] );
        // Use masking?
        $frame_head_binstr .= $masked ? '1' : '0';
        // 7 bits of payload length...
        $payload_length = strlen( $payload );
        if ( $payload_length > 65535 ) {
            $frame_head_binstr .= decbin( 127 );
            $frame_head_binstr .= sprintf( '%064b', $payload_length );
        } elseif ( $payload_length > 125 ) {
            $frame_head_binstr .= decbin( 126 );
            $frame_head_binstr .= sprintf( '%016b', $payload_length );
        } else {
            $frame_head_binstr .= sprintf( '%07b', $payload_length );
        }
        $frame = '';
        // Write frame head to frame.
        foreach ( str_split( $frame_head_binstr, 8 ) as $binstr ) $frame .= chr( bindec( $binstr ) );
        // Handle masking
        if ( $masked ) {
            // generate a random mask:
            $mask = '';
            for ( $i = 0; $i < 4; $i++ ) $mask .= chr( rand( 0, 255 ) );
            $frame .= $mask;
        }
        // Append payload to frame:
        for ( $i = 0; $i < $payload_length; $i++ ) {
            $frame .= ( $masked === TRUE ) ? $payload[ $i ] ^ $mask[ $i % 4 ] : $payload[ $i ];
        }
        $this->write( $frame );
    }

    public function receive()
    {
        if ( !$this->is_connected ) $this->connect(); /// @todo This is a client function, fixme!
        $this->huge_payload = '';
        $response = NULL;
        while ( is_null( $response ) ) $response = $this->receive_fragment();

        return $response;
    }

    protected function receive_fragment()
    {
        // Just read the main fragment information first.
        $data = $this->read( 2 );
        // Is this the final fragment?  // Bit 0 in byte 0
        /// @todo Handle huge payloads with multiple fragments.
        $final = (boolean)( ord( $data[ 0 ] ) & 1 << 7 );
        // Should be unused, and must be false…  // Bits 1, 2, & 3
        $rsv1 = (boolean)( ord( $data[ 0 ] ) & 1 << 6 );
        $rsv2 = (boolean)( ord( $data[ 0 ] ) & 1 << 5 );
        $rsv3 = (boolean)( ord( $data[ 0 ] ) & 1 << 4 );
        // Parse opcode
        $opcode_int = ord( $data[ 0 ] ) & 31; // Bits 4-7
        $opcode_ints = array_flip( self::$opcodes );
        if ( !array_key_exists( $opcode_int, $opcode_ints ) ) {
            throw new Exception( "Bad opcode in websocket frame: $opcode_int" );
        }
        $opcode = $opcode_ints[ $opcode_int ];
        // record the opcode if we are not receiving a continutation fragment
        if ( $opcode !== 'continuation' ) {
            $this->last_opcode = $opcode;
        }
        // Masking?
        $mask = (boolean)( ord( $data[ 1 ] ) >> 7 );  // Bit 0 in byte 1
        $payload = '';
        // Payload length
        $payload_length = (integer)ord( $data[ 1 ] ) & 127; // Bits 1-7 in byte 1
        if ( $payload_length > 125 ) {
            if ( $payload_length === 126 ) $data = $this->read( 2 ); // 126: Payload is a 16-bit unsigned int
            else                         $data = $this->read( 8 ); // 127: Payload is a 64-bit unsigned int
            $payload_length = bindec( self::sprintB( $data ) );
        }
        // Get masking key.
        if ( $mask ) $masking_key = $this->read( 4 );
        // Get the actual payload, if any (might not be for e.g. close frames.
        if ( $payload_length > 0 ) {
            $data = $this->read( $payload_length );
            if ( $mask ) {
                // Unmask payload.
                for ( $i = 0; $i < $payload_length; $i++ ) $payload .= ( $data[ $i ] ^ $masking_key[ $i % 4 ] );
            } else $payload = $data;
        }
        if ( $opcode === 'close' ) {
            // Get the close status.
            if ( $payload_length >= 2 ) {
                $status_bin = $payload[ 0 ] . $payload[ 1 ];
                $status = bindec( sprintf( "%08b%08b", ord( $payload[ 0 ] ), ord( $payload[ 1 ] ) ) );
                $this->close_status = $status;
                $payload = substr( $payload, 2 );
                if ( !$this->is_closing ) $this->send( $status_bin . 'Close acknowledged: ' . $status, 'close', TRUE ); // Respond.
            }
            if ( $this->is_closing ) $this->is_closing = FALSE; // A close response, all done.
            // And close the socket.
            fclose( $this->socket );
            $this->is_connected = FALSE;
        }
        // if this is not the last fragment, then we need to save the payload
        if ( !$final ) {
            $this->huge_payload .= $payload;

            return NULL;
        } // this is the last fragment, and we are processing a huge_payload
        else if ( $this->huge_payload ) {
            // sp we need to retreive the whole payload
            $payload = $this->huge_payload .= $payload;
            $this->huge_payload = NULL;
        }

        return $payload;
    }

    /**
     * @param int    $status
     * @param string $message
     * @return null|string
     * @throws Exception
     */
    public function close( $status = 1000, $message = 'ttfn' )
    {
        $status_binstr = sprintf( '%016b', $status );
        $status_str = '';
        foreach ( str_split( $status_binstr, 8 ) as $binstr ) $status_str .= chr( bindec( $binstr ) );
        $this->send( $status_str . $message, 'close', TRUE );
        $this->is_closing = TRUE;
        $response = $this->receive(); // Receiving a close frame will close the socket now.
        return $response;
    }

    protected function write( $data )
    {
        $written = fwrite( $this->socket, $data );
        if ( $written < strlen( $data ) ) {
            throw new Exception(
                "Could only write $written out of " . strlen( $data ) . " bytes."
            );
        }
    }

    protected function read( $length )
    {
        $data = '';
        while ( strlen( $data ) < $length ) {
            $buffer = fread( $this->socket, $length - strlen( $data ) );
            if ( $buffer === FALSE ) {
                $metadata = stream_get_meta_data( $this->socket );
                throw new Exception(
                    'Broken frame, read ' . strlen( $data ) . ' of stated '
                    . $length . ' bytes.  Stream state: '
                    . json_encode( $metadata )
                );
            }
            if ( $buffer === '' ) {
                $metadata = stream_get_meta_data( $this->socket );
                throw new Exception(
                    'Empty read; connection dead?  Stream state: ' . json_encode( $metadata )
                );
            }
            $data .= $buffer;
        }

        return $data;
    }

    /**
     * Helper to convert a binary to a string of '0' and '1'.
     */
    protected static function sprintB( $string )
    {
        $return = '';
        for ( $i = 0; $i < strlen( $string ); $i++ ) $return .= sprintf( "%08b", ord( $string[ $i ] ) );

        return $return;
    }
}


// cmd
// php ws_cli.php port '{"cmd":"pong"}'

$client = new Client( "ws://localhost:{$argv[1]}" );
$client->send( $argv[ 2 ] );
echo $client->receive();