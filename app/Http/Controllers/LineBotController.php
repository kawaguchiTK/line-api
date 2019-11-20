<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

class LineBotController extends Controller
{
        public function parrot(Request $request)
        {
                Log::debug($request->header());
                Log::debug($request->input());

                // linebotクラスのインスタンス化
                $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
                $lineBot = new LINEBot($httpClient,['channelSecret' => env('LINE_CHANNEL_SECRET')]);

                $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
                $lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);

            }
}
