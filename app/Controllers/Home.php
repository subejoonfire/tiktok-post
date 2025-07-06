<?php

namespace App\Controllers;

class Home extends BaseController
{
    protected $client_key = 'sbawqd9rkfix8f59ed';
    protected $client_secret = 'AxVCevAq0lSqTjq7bFZ8RSbn4IhTE52w';
    protected $redirect_uri = 'https://6f78-140-213-204-80.ngrok-free.app';

    public function index()
    {
        $session = session();
        $data['access_token'] = $session->get('access_token');

        // Generate TikTok OAuth URL
        $data['auth_url'] = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
            'client_key' => $this->client_key,
            'scope' => 'video.upload',
            'response_type' => 'code',
            'redirect_uri' => $this->redirect_uri,
            'state' => bin2hex(random_bytes(6))
        ]);

        return view('tiktok_form', $data);
    }

    public function callback()
    {
        $session = session();

        $returnedState = $this->request->getGet('state');
        $storedState = $session->get('oauth_state');
        if ($returnedState !== $storedState) {
            die("Invalid state parameter");
        }
        $code = $this->request->getGet('code');
        if (!$code) {
            die("Authorization code missing");
        }
        $client = \Config\Services::curlrequest();
        $response = $client->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'form_params' => [
                'client_key' => $this->client_key,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirect_uri,
                'code_verifier' => $session->get('code_verifier') // Penting untuk PKCE
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        $access_token = $result['data']['access_token'] ?? null;

        if ($access_token) {
            $session->set('access_token', $access_token);
            return redirect()->to('/tiktok');
        }

        // Tampilkan error detail jika ada
        return "Error: " . print_r($result, true);
    }

    public function upload()
    {
        $session = session();
        $access_token = $session->get('access_token');
        if (!$access_token) return redirect()->to('/tiktok');

        $video = $this->request->getFile('video');
        $title = $this->request->getPost('title');

        if (!$video->isValid() || $video->getMimeType() !== 'video/mp4') {
            return redirect()->back()->with('error', 'File harus berupa video MP4');
        }

        $tempPath = WRITEPATH . 'uploads/' . $video->getRandomName();
        $video->move(WRITEPATH . 'uploads', basename($tempPath));

        // 1. Init upload
        $client = \Config\Services::curlrequest();
        $response = $client->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
            'headers' => [
                'Authorization' => "Bearer $access_token",
                'Content-Type'  => 'application/json'
            ],
            'json' => [
                'post_info' => [
                    'title' => $title
                ]
            ]
        ]);

        $init = json_decode($response->getBody(), true);
        if (!isset($init['data']['upload_url'])) {
            unlink($tempPath);
            return redirect()->back()->with('error', 'Gagal inisialisasi upload.');
        }

        $uploadUrl = $init['data']['upload_url'];
        $videoId = $init['data']['video_id'];

        // 2. Upload video via PUT
        $videoData = file_get_contents($tempPath);
        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $videoData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: video/mp4'
        ]);
        $uploadRes = curl_exec($ch);
        curl_close($ch);

        // 3. Publish video
        $client->post('https://open.tiktokapis.com/v2/post/publish/video/complete/', [
            'headers' => [
                'Authorization' => "Bearer $access_token",
                'Content-Type'  => 'application/json'
            ],
            'json' => ['video_id' => $videoId]
        ]);

        unlink($tempPath);
        return redirect()->to('/tiktok')->with('success', 'Video berhasil diposting ke TikTok!');
    }
}
