<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArcGISController;


Route::get('/', [ArcGISController::class, 'map'])
    ->name('home');
Route::get('/arcgis/login', [ArcGISController::class, 'login']);

Route::get('/arcgis/callback', [ArcGISController::class, 'callback']);

Route::get('/arcgis/profile', [ArcGISController::class, 'profile']);

Route::get('/arcgis/layers', [ArcGISController::class, 'layers']);

Route::get('/arcgis/features', [ArcGISController::class, 'features'])
    ->name('arcgis.features');

// Route::get('/arcgis/map', [ArcGISController::class, 'map'])
//     ->name('arcgis.map');


// Tes pengambilan token
Route::get('/arcgis/test-connection', function () {
    $connection = \App\Models\ArcGISConnection::find(1);

    if (!$connection) {
        return response()->json([
            'status' => false,
            'message' => 'Koneksi belum tersimpan.'
        ], 404);
    }

    try {
        $token = \Illuminate\Support\Facades\Crypt::decryptString(
            $connection->access_token
        );

        return response()->json([
            'status' => true,
            'username' => $connection->username,
            'token_available' => !empty($token),
            'expires_at' => $connection->expires_at,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Gagal membaca token.'
        ], 500);
    }
});


// shape layer
Route::get('/arcgis/features/areal', [ArcGISController::class, 'areal']);
Route::get('/arcgis/features/jalan', [ArcGISController::class, 'jalan']);
Route::get('/arcgis/features/sungai', [ArcGISController::class, 'sungai']);
