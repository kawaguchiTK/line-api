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
        $lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);

        // 署名の検証
        $signature = $request->header('x-line-signature');

        if (!$lineBot->validateSignature($request->getContent(), $signature)) {
            abort(400, 'invalid signature');
        }

        // イベント(lineから送られた画像、テキストなどの情報)
        $events = $lineBot->parseEventRequest($request->getContent(), $signature);

        // テキスト以外のはログ出力してスキップ
        foreach ($events as $event) {
            if (!($event instanceof TextMessage)) {
                Log::debug('Non text message has come');
                continue;
            }

            $replyToken = $event->getReplyToken();
            $replyText = $event->getText();

            // 最後に保存したリマインドを取得
            $lastRemind = $this->getLastRemind();

            // 一番初めの登録 or 次のリマインド登録
            if (empty($lastRemind) || isset($lastRemind->remind_execute_time))
            {
                $returnText =  "「" . $replyText  . "」ですね。覚えました。大体、何分後にリマインドしますか？";
                $this->saveRemindContent($replyText);
                // リマインド内容を登録後、時間を入力していない場合
            } elseif (empty($lastRemind->remind_regist_time) && !$this->checkNum($replyText))
            {
                $returnText =  "「" . $lastRemind->content  . "」の時間登録が終わっていません。大体、何分後にリマインドしますか？";
                // リマインド内容を登録後、時間を入力した場合
            } elseif (!$lastRemind->remind_regist_time && $this->checkNum($replyText))
            {
                $returnText =   "大体、".$replyText  . "分後ですね。了解しました。";
                // リマインド時間を登録
                $this->saveRemindRegistTime($lastRemind->id, $replyText);
            // 登録したリマインドが通知される前に登録しようとした場合
            } elseif (isset($lastRemind->remind_regist_time) && empty(($lastRemind->remind_execute_time)))
            {
                $returnText = "２件連続登録できません。リマインドされるまで待ってくださいね";
            }

            $lineBot->replyText($replyToken, $returnText);
        }
    }

    public function saveRemindContent($replyText)
    {
        $remind = new Remind();
        $remind->content = $replyText;
        $remind->save();
    }

    public function saveRemindRegistTime($lastRemindId, $replyText)
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
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first();

        return $remind;
    }

    public function checkNum($target)
    {
        if (is_numeric($target)) {
            return true;
        } else {
            return false;
        }
    }
}
