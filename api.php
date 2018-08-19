<?php
//error_reporting(E_WARNING);
	//echo '<meta charset="utf-8">';
	$hostname=$_GET['server'];
	$port=$_GET['port'];

	function packData($data)
    {
        return packVarInt(strlen($data)) . $data;
    }

    function unpackVarInt($fp, &$response=null)
    {
        $int = 0;
        $pos = 0;
        while (true) {
            $chunk = fread($fp, 1);
            if ($response !== null) {
                $response .= $chunk;
            }
            $byte = ord($chunk);
            $int |= ($byte & 0x7F) << $pos++ * 7;
            if ($pos > 5) {
                throw new Exception('VarInt too big');
            }
            if (($byte & 0x80) !== 128) {
                break;
            }
        }
        return $int;
    }

    function packVarInt($int)
    {
        $varInt = '';
        while (true) {
            if (($int & 0xFFFFFF80) === 0) {
                $varInt .= chr($int);
                return $varInt;
            }
            $varInt .= chr($int & 0x7F | 0x80);
            $int >>= 7;
        }
    }
	$handshakePacket=packData(//握手包
        chr(0) .//包id
        packVarInt(4) .//协议版本号
        packData($hostname) .//ip
        pack('n', (int)$port) .//端口
        packVarInt(1)//状态，1是握手
    );
    $statusRequestPacket = packData(chr(0));//后面跟小包
	
	$time = microtime(true);//当前时间
	$fp = stream_socket_client('tcp://' . $hostname . ':' . $port, $errno, $errmsg, 5);//连接
	stream_set_timeout($fp, 5);//设置timeout
	if (!$fp) {
        echo '{"err":1,"msg":"'.$errmsg.'"}';
        exit(1);
    }
	fwrite($fp, $handshakePacket);//发送握手包
    fwrite($fp, $statusRequestPacket);//接小包
	$response = '';//返回
	unpackVarInt($fp, $response); // 获得包长度
	$time = round((microtime(true)-$time)*1000);//设置时间
	unpackVarInt($fp, $response); // 获得包id
	$jsonLength = unpackVarInt($fp, $response);//下面是一个motd信息，这个是获得motd信息长度
	$jsonString = '';//motd全部数据
    while (strlen($jsonString) < $jsonLength) {//循环读数据
        $chunk = fread($fp, 2048);
        $jsonString .= $chunk;
    }
    $response .= $jsonString;//json变成返回数据
	fclose($fp);//关闭流
	echo $jsonString;
?>