<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PortfolioController extends Controller
{
    public function index()
    {
        $portfolio = Cache::rememberForever('portfolio_data', function () {
            return PortfolioSetting::first();
        });

        if (!$portfolio) {
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

        return response()->json($portfolio->data);
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

        Cache::forget('portfolio_data');

        return response()->json([
            'message' => 'Portfolio saved successfully',
            'data' => $portfolio->data
        ]);
    }
}
