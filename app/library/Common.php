<?php
namespace App\library;
use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use Illuminate\Support\Facades\Log;

class Common
{

    public function getLineBot(){
        // linebotクラスのインスタンス化
        $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
        $lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);
        return $lineBot;
    }

    public function getEvents($request,$lineBot)
    {
        // 署名の検証
        $signature = $request->header('x-line-signature');

        if (!$lineBot->validateSignature($request->getContent(), $signature)) {
            abort(400, 'invalid signature');
        }

        // イベント(lineから送られた画像、テキストなどの情報)
        $events = $lineBot->parseEventRequest($request->getContent(), $signature);
        Log::debug($events);

        return $events;
    }
}