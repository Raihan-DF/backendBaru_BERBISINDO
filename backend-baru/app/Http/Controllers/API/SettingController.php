<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = Setting::where('user_id', $request->user()->id)->first();

        if (!$settings) {
            $settings = Setting::create([
                'user_id' => $request->user()->id,
            ]);
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'dark_mode' => 'boolean',
            'language' => 'string|in:id,en',
            'preferences' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = Setting::where('user_id', $request->user()->id)->first();

        if (!$settings) {
            $settings = Setting::create([
                'user_id' => $request->user()->id,
            ]);
        }

        $settings->update($request->only([
            'email_notifications',
            'dark_mode',
            'language',
            'preferences',
        ]));

        return response()->json($settings);
    }
}
