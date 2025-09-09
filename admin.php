<?php

if(!isset($_SESSION['userId']) || $_SESSION['rol'] !== 'admin'){
    header("Location: /login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - Draftosaurus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #3a0ca3, #000000); min-height: 100vh; }
        .card { background-color: rgba(0,0,0,0.75); color: #fff; }
        .modal-content { background-color: #1a1a1a; color: #fff; }
        .badge { font-size: 0.9em; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/views/admin.html'; ?>
    <script>
        const currentUser = <?php echo json_encode($_SESSION); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/admin.js"></script>
</body>
</html>