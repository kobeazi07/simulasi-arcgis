<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\ArcGISConnection;

class ArcGISController extends Controller
{
    /**
     * Login OAuth ArcGIS
     */
    public function login()
    {
        $state = Str::random(40);

        session(['arcgis_oauth_state' => $state]);

        $query = http_build_query([
            'client_id' => config('services.arcgis.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.arcgis.redirect_uri'),
            'state' => $state,
        ]);

        return redirect(
            config('services.arcgis.portal') .
                '/sharing/rest/oauth2/authorize?' . $query
        );
    }

    /**
     * Callback OAuth ArcGIS
     */

    // callback insert  koneksi
    public function callback(Request $request)
    {
        $code = $request->query('code');

        // Periksa authorization code
        if (!$code) {
            return response()->json([
                'status' => false,
                'message' => 'Authorization code tidak ditemukan.',
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ], 400);
        }

        // Tukar authorization code dengan access token
        $response = Http::asForm()->post(
            config('services.arcgis.portal') . '/sharing/rest/oauth2/token',
            [
                'client_id' => config('services.arcgis.client_id'),
                'client_secret' => config('services.arcgis.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.arcgis.redirect_uri'),
                'f' => 'json',
            ]
        );

        $data = $response->json();

        // Periksa respons ArcGIS
        if (!$response->successful() || empty($data['access_token'])) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mendapatkan access token.',
                'response' => $data,
            ], 400);
        }

        // Simpan token menggunakan Eloquent Model
        $connection = ArcGISConnection::updateOrCreate(
            ['id' => 1],
            [
                'username' => $data['username'] ?? null,

                'access_token' => Crypt::encryptString(
                    $data['access_token']
                ),

                'refresh_token' => !empty($data['refresh_token'])
                    ? Crypt::encryptString($data['refresh_token'])
                    : null,

                'expires_at' => now()->addSeconds(
                    $data['expires_in'] ?? 1800
                ),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Koneksi ArcGIS berhasil disimpan.',
            'username' => $connection->username,
            'database_id' => $connection->id,
        ]);
    }
    // public function callback(Request $request)
    // {
    //     $code = $request->query('code');

    //     if (!$code) {
    //         return response()->json([
    //             'message' => 'Authorization code tidak ditemukan.'
    //         ], 400);
    //     }

    //     // Tukar authorization code dengan access token
    //     $response = Http::asForm()->post(
    //         config('services.arcgis.portal') . '/sharing/rest/oauth2/token',
    //         [
    //             'client_id' => config('services.arcgis.client_id'),
    //             'client_secret' => config('services.arcgis.client_secret'),
    //             'grant_type' => 'authorization_code',
    //             'code' => $code,
    //             'redirect_uri' => config('services.arcgis.redirect_uri'),
    //             'f' => 'json',
    //         ]
    //     );

    //     $data = $response->json();

    //     // Periksa apakah token berhasil diperoleh
    //     if (!$response->successful() || empty($data['access_token'])) {
    //         return response()->json([
    //             'message' => 'Gagal mendapatkan access token.',
    //             'error' => $data
    //         ], 400);
    //     }

    //     // Simpan koneksi ArcGIS ke database
    //     ArcGISConnection::updateOrCreate(
    //         ['id' => 1],
    //         [
    //             'username' => $data['username'] ?? null,

    //             'access_token' => Crypt::encryptString(
    //                 $data['access_token']
    //             ),

    //             'refresh_token' => !empty($data['refresh_token'])
    //                 ? Crypt::encryptString($data['refresh_token'])
    //                 : null,

    //             'expires_at' => now()->addSeconds(
    //                 $data['expires_in'] ?? 1800
    //             ),
    //         ]
    //     );

    //     return redirect('/')->with(
    //         'success',
    //         'Koneksi ArcGIS Online berhasil.'
    //     );
    // }

    /**
     * Mendapatkan Access Token yang masih berlaku.
     * Jika kedaluwarsa, perbarui menggunakan Refresh Token.
     */
    private function getArcGISAccessToken()
    {
        $token = session('arcgis_access_token');

        $expiresAt = session('arcgis_expires_at');

        // Token masih berlaku
        if (
            $token &&
            $expiresAt &&
            now()->timestamp < ($expiresAt - 60)
        ) {
            return $token;
        }

        $refreshToken = session('arcgis_refresh_token');

        if (!$refreshToken) {
            return null;
        }

        // Refresh Access Token
        $response = Http::asForm()->post(
            config('services.arcgis.portal') .
                '/sharing/rest/oauth2/token',
            [
                'client_id' => config('services.arcgis.client_id'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'f' => 'json',
            ]
        );

        $data = $response->json();

        if (
            !$response->successful() ||
            isset($data['error']) ||
            empty($data['access_token'])
        ) {
            session()->forget([
                'arcgis_access_token',
                'arcgis_refresh_token',
                'arcgis_expires_at',
            ]);

            return null;
        }

        session([
            'arcgis_access_token' => $data['access_token'],

            'arcgis_expires_at' => now()
                ->addSeconds($data['expires_in'] ?? 1800)
                ->timestamp,
        ]);

        return $data['access_token'];
    }

    /**
     * Profil ArcGIS
     */
    public function profile()
    {
        $token = $this->getArcGISAccessToken();

        if (!$token) {
            return redirect('/arcgis/login');
        }

        $response = Http::get(
            config('services.arcgis.portal') .
                '/sharing/rest/community/self',
            [
                'f' => 'json',
                'token' => $token,
            ]
        );

        return response()->json($response->json());
    }

    /**
     * Mengambil daftar layer
     */
    public function layers()
    {
        $token = $this->getArcGISAccessToken();


        if (!$token) {
            return response()->json([
                'message' => 'Autentikasi ArcGIS diperlukan.',
            ], 401);
        }

        $url = config('services.arcgis.feature_layer_url');

        $response = Http::get($url, [
            'f' => 'json',
            'token' => $token,
        ]);

        return response()->json(
            $response->json(),
            $response->status()
        );
    }

    /**
     * Mengambil data polygon
     */
    public function features()
    {
        $token = $this->getSharedArcGISAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'Koneksi ArcGIS belum tersedia atau perlu otorisasi ulang administrator.'
            ], 401);
        }

        $response = Http::timeout(30)->get(
            config('services.arcgis.feature_layer_url') . '/0/query',
            [
                'where' => '1=1',
                'outFields' => '*',
                'returnGeometry' => 'true',
                'outSR' => 4326,
                'f' => 'json',
                'token' => $token,
            ]
        );

        $data = $response->json();

        if (!$response->successful() || isset($data['error'])) {
            return response()->json([
                'message' => 'Gagal mengambil data ArcGIS.',
                'detail' => $data['error'] ?? $response->body(),
            ], 502);
        }

        return response()->json($data);
    }
    private function getSharedArcGISAccessToken()
    {
        $connection = ArcGISConnection::find(1);

        if (!$connection) {
            return null;
        }

        try {

            // Periksa masa berlaku Access Token
            if (
                $connection->expires_at &&
                now()->lt($connection->expires_at->copy()->subMinutes(2))
            ) {
                return Crypt::decryptString(
                    $connection->access_token
                );
            }

            // Pastikan Refresh Token tersedia
            if (!$connection->refresh_token) {
                return null;
            }

            // Gunakan lock agar tidak terjadi refresh bersamaan
            return Cache::lock('arcgis-token-refresh', 15)
                ->block(10, function () {

                    $connection = ArcGISConnection::find(1);

                    if (
                        $connection->expires_at &&
                        now()->lt($connection->expires_at->copy()->subMinutes(2))
                    ) {
                        return Crypt::decryptString(
                            $connection->access_token
                        );
                    }

                    $refreshToken = Crypt::decryptString(
                        $connection->refresh_token
                    );

                    $response = Http::asForm()->timeout(20)->post(
                        config('services.arcgis.portal') .
                            '/sharing/rest/oauth2/token',
                        [
                            'client_id' => config('services.arcgis.client_id'),
                            'client_secret' => config('services.arcgis.client_secret'),
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $refreshToken,
                            'f' => 'json',
                        ]
                    );

                    $data = $response->json();

                    if (
                        !$response->successful() ||
                        empty($data['access_token'])
                    ) {
                        logger()->error('ArcGIS token refresh gagal', [
                            'response' => $data,
                        ]);

                        return null;
                    }

                    $connection->update([
                        'access_token' => Crypt::encryptString(
                            $data['access_token']
                        ),

                        'refresh_token' => !empty($data['refresh_token'])
                            ? Crypt::encryptString($data['refresh_token'])
                            : $connection->refresh_token,

                        'expires_at' => now()->addSeconds(
                            $data['expires_in'] ?? 1800
                        ),
                    ]);

                    return $data['access_token'];
                });
        } catch (\Throwable $e) {

            logger()->error('ArcGIS connection error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
    /**
     * Halaman WebGIS
     */
    public function map()
    {
        return view('arcgis.map');
    }
}
