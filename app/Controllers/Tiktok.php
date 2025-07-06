<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class Tiktok extends BaseController
{
    protected $client_key = 'sbaw5r2gw9v3qp7lej';
    protected $client_secret = 'A6aJ51K4QvBEXu4FJqW8XBIIqVAl2Bqm';
    protected $redirect_uri = 'https://c603-140-213-67-126.ngrok-free.app/tiktok/callback';
    public function index()
    {
        return view('tiktok_login');
    }

    public function login()
    {
        $verifier = bin2hex(random_bytes(32));
        session()->set('code_verifier', $verifier);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(6));
        session()->set('oauth_state', $state);

        $auth_url = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
            'client_key'             => $this->client_key,
            'scope'                  => 'video.publish',
            'response_type'          => 'code',
            'redirect_uri'           => $this->redirect_uri,
            'state'                  => $state,
            'code_challenge'         => $challenge,
            'code_challenge_method'  => 'S256'
        ]);

        return redirect()->to($auth_url);
    }

    public function callback()
    {
        $code = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        $verifier = session()->get('code_verifier');

        if (!$code || !$verifier || $state !== session()->get('oauth_state')) {
            return 'Invalid OAuth response.';
        }

        $client = \Config\Services::curlrequest();
        $response = $client->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'form_params' => [
                'client_key'     => $this->client_key,
                'client_secret'  => $this->client_secret,
                'code'           => $code,
                'grant_type'     => 'authorization_code',
                'redirect_uri'   => $this->redirect_uri,
                'code_verifier'  => $verifier
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        $access_token = $result['access_token'] ?? null;

        if ($access_token) {
            session()->set('access_token', $access_token);
            return redirect()->to('/tiktok/dashboard');
        }

        return "Login gagal: <pre>" . print_r($result, true) . "</pre>";
    }

    public function dashboard()
    {
        if (!session()->get('access_token')) {
            return redirect()->to('/tiktok');
        }
        return view('tiktok_dashboard');
    }

    public function upload()
    {
        $access_token = session()->get('access_token');
        if (!$access_token) return redirect()->to('/tiktok');

        $video = $this->request->getFile('video');
        $title = $this->request->getPost('title');

        if (!$video->isValid() || $video->getMimeType() !== 'video/mp4') {
            return redirect()->back()->with('error', 'File harus video MP4');
        }

        $filename = $video->getRandomName();
        $video->move(ROOTPATH . 'public/videos', $filename);
        $publicUrl = base_url('videos/' . $filename);

        $client = \Config\Services::curlrequest();
        $response = $client->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
            'headers' => [
                'Authorization'  => "Bearer $access_token",
                'Content-Type'   => 'application/json',
                'X-Tt-Client-Id' => $this->client_key
            ],
            'json' => [
                'source_info' => [
                    'source'    => 'PULL_FROM_URL',
                    'video_url' => $publicUrl
                ],
                'post_info' => [
                    'title' => $title
                ]
            ]
        ]);

        $init = json_decode($response->getBody(), true);

        print_r($init);
        die;
        if (!isset($init['data']['publish_id'])) {
            return redirect()->back()->with('error', 'Upload gagal: ' . json_encode($init));
        }

        return redirect()->to('/tiktok/dashboard')->with('success', 'Video berhasil dikirim ke TikTok, publish ID: ' . $init['data']['publish_id']);
    }



    public function logout()
    {
        session()->destroy();
        return redirect()->to('/tiktok');
    }
}
