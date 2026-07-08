<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSetting;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index()
    {
        $portfolio = PortfolioSetting::first();

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

        return response()->json([
            'message' => 'Portfolio saved successfully',
            'data' => $portfolio->data
        ]);
    }
}
