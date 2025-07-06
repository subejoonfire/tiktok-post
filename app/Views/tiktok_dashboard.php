<!DOCTYPE html>
<html>

<head>
    <title>Upload ke TikTok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light py-5">
    <div class="container">
        <h2 class="mb-4">Upload Video ke TikTok</h2>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
        <?php elseif (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
        <?php endif; ?>

        <form action="<?= base_url('/tiktok/upload') ?>" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Judul Video</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="video" class="form-label">File Video (.mp4)</label>
                <input type="file" name="video" class="form-control" accept="video/mp4" required>
            </div>

            <button type="submit" class="btn btn-success">Upload & Publish</button>
            <a href="<?= base_url('/tiktok/logout') ?>" class="btn btn-secondary float-end">Logout</a>
        </form>
    </div>
</body>

</html>