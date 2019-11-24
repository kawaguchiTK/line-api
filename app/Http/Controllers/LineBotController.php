<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\Event\MessageEvent\TextMessage;

class LineBotController extends Controller
{
        public function parrot(Request $request)
        {
  
                // linebotクラスのインスタンス化
                $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
                $lineBot = new LINEBot($httpClient,['channelSecret' => env('LINE_CHANNEL_SECRET')]);
                
                // 署名の検証
                $signature = $request->header('x-line-signature');
                if (!$lineBot->validateSignature($request->getContent(), $signature))
                {
                    abort(400,'invalid signature');
                }

                // イベント(lineから送られた画像、テキストなどの情報)
                $events = $lineBot->parseEventRequest($request->getContent(),$signature);
  
                // テキスト以外のはログ出力
                foreach ($events as $event)
                {
                    if (!($event instanceof TextMessage))
                    {
                        Log::debug('Non text message has come');
                        continue;
                    }

                    // おうむ返し
                    $replyToken = $event->getReplyToken();
                    $replyText = $event->getText();


                    $receptionText =  "「" . $replyText  . "」ですね。何時間後にリマインドしますか？";



                    $lineBot->replyText($replyToken,$receptionText);
                }

            }

            public function defineHour($requestHour)
            {
                // 数値のみにする(時間単位)
                $requestHour = preg_replace('[^0-9]','',$requestHour);

                $remindTime = Carbon::now()->addHours($requestHour);
                return $remindTime;
            }





}
