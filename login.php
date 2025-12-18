<?php 
// login.php
session_start();
// ถ้า Login แล้วให้เด้งไปหน้าแรกเลย
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบศูนย์พักพิง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; border-radius: 15px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .login-header { background: #0d6efd; color: white; border-radius: 15px 15px 0 0; padding: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="login-header">
        <h4 class="mb-0 fw-bold">เข้าสู่ระบบ</h4>
        <small>ระบบบริหารจัดการศูนย์พักพิงชั่วคราว</small>
    </div>
    <div class="card-body p-4">
        <!-- แสดง Error -->
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form action="login_db.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">ชื่อผู้ใช้งาน</label>
                <input type="text" class="form-control" id="username" name="username" required placeholder="admin หรือ staff01">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="******">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">เข้าใช้งาน</button>
        </form>
        
        <div class="text-center mt-3">
            <small class="text-muted">Username: admin / Pass: 123456</small><br>
            <small class="text-muted">Username: staff01 / Pass: 123456</small>
        </div>
    </div>
</div>

</body>
</html>