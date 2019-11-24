<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Remind;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use Carbon\Carbon;

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

                // テキスト以外のはログ出力してスキップ
                foreach ($events as $event)
                {
                    if (!($event instanceof TextMessage))
                    {
                        Log::debug('Non text message has come');
                        continue;
                    }

                    $replyToken = $event->getReplyToken();
                    $replyText = $event->getText();

                    // 最後に保存したリマインドを取得
                    $lastRemind = $this->getLastRemind();

                    if ($lastRemind->remind_regist_time)
                    {
                        $returnText =  "「" . $replyText  . "」ですね。内容を保存しました。何分後にリマインドしますか？";
                        $this->saveRemindContent($replyText);
                    // リマインド内容を登録後、時間を入力していない場合
                    } elseif (!$lastRemind->remind_regist_time && !$this->checkNum($replyText))
                    {
                        $returnText =  "「" . $lastRemind->content  . "」の時間登録が終わっていません。何分後にリマインドしますか？";
                    // リマインド内容を登録後、時間を入力した場合
                    } elseif (!$lastRemind->remind_regist_time && $this->checkNum($replyText)) 
                    {
                        $returnText =   $replyText  . "分後ですね。了解しました。";
                        // リマインド時間を登録
                        $this->saveRemindRegistTime($lastRemind->id,$replyText);
                    }

                    $lineBot->replyText($replyToken,$returnText);
                }

            }

            public function defineHour($requestHour)
            {
                // 数値のみにする(時間単位)
                $requestHour = preg_replace('[^0-9]','',$requestHour);

                $remindTime = Carbon::now()->addHours($requestHour);
                return $remindTime;
            }

            public function saveRemindContent($replyText)
            {
                $remind = new Remind();
                $remind->content = $replyText;
                $remind->save();
            }

            public function saveRemindRegistTime($lastRemindId,$replyText)
            {
                $registTime = Carbon::now()->addMinutes($replyText)->format('Y-m-d H:i:s');
                Log::debug($registTime);
                $remind = Remind::find($lastRemindId);

                $remind->remind_regist_time = $registTime;
                $remind->save();
            }

            public function getLastRemind()
            {
                $remind = DB::table('reminds')
                                        ->orderBy('id','desc')
                                        ->limit(1)
                                        ->first();

                return $remind;
            }

            public function checkNum($target)
            {
                if (is_numeric($target))
                {
                    return true;
                } else 
                {
                    return false;
                }
            }


}
