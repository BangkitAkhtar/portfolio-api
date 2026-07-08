<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PortfolioController extends Controller
{
    public function index()
    {
        $data = Cache::rememberForever('portfolio_settings_data', function () {
            $portfolio = PortfolioSetting::first();
            return $portfolio ? $portfolio->data : null;
        });

        if (!$data) {
            return response()->json([
                "profile" => [
                    "name" => "",
                    "headline" => "",
                    "email" => "",
                    "linkedin" => "",
                    "cvLink" => "",
                    "image" => "",
                    "about" => ""
                ],
                "experiences" => [],
                "education" => [],
                "certifications" => [],
                "trainings" => [],
                "projects" => [],
                "volunteers" => [],
                "awards" => [],
                "skills" => [],
                "languages" => []
            ]);
        }

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $portfolio = PortfolioSetting::first();

        if ($portfolio) {
            $portfolio->update([
                'data' => $request->all()
            ]);
        } else {
            $portfolio = PortfolioSetting::create([
                'data' => $request->all()
            ]);
        }

        Cache::forget('portfolio_settings_data');

        return response()->json([
            'message' => 'Portfolio saved successfully',
            'data' => $portfolio->data
        ]);
    }
}
