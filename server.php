<?php
/**
 * websocket服务器端程序
 * */

//require "一个dispatcher，用来将处理转发业务实现群组或者私聊";
require __DIR__."/dispatcher.php";

$server = new swoole_websocket_server("0.0.0.0", 22223);

$server->on("open", function($server, $request) {
    echo "client {$request->fd} connected, remote address: {$request->server['remote_addr']}:{$request->server['remote_port']}\n";
    $welcomemsg = "Welcome {$request->fd} joined this chat room.";
    // TODO 这里可以看出设计有问题，构造方法里面应该是通用的逻辑，而不是针对某一个方法有效
    //$dispatcher = new Dispatcher("");
    //$dispatcher->sendPublicChat($server, $welcomemsg);
    foreach($server->connections as $key => $fd) {
        $server->push($fd, $welcomemsg);
    }
});

$server->on("message", function($server, $frame) {
    /*
    $chatmsg = json_decode($frame->data, true);
    if($chatmsg['chattype'] == "publicchat") {
        $usermsg = "Client {$frame->fd} 说：".$frame->data;
        foreach($server->connections as $key => $fd) {
            $server->push($fd, $usermsg);
        }
    }else if($chatmsg['chattype'] == "privatechat") {
        $usermsg = "Client{$frame->fd} 对 Client{$chatmsg['chatto']} 说： {$chatmsg['chatmsg']}.";
        $server->push(intval($chatmsg['chatto']), $usermsg);
    }
     */
    $data = json_decode($frame->data, true);
    var_dump($frame->data);
    $from = intval($frame->fd);
    $type = $data['type'];
    $dispatcher = Dispatcher::getInstance();
    if($type == "chat") {
        $dispatcher->handleChat($server, $from, $data['params']);
    }else if($type == "action") {
        $dispatcher->handleAction($server, $from, $data['params']);
    }
});

$server->on("close", function($server, $fd) {
    //$goodbyemsg = "Client {$fd} leave this chat room.";
    $data = array("action"=>"unregisteruser");
    Dispatcher::getInstance()->handleAction($server, $fd, $data);
});

$server->start();

