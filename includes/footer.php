</div> <!-- ปิด .main-content ที่เปิดไว้ใน header.php -->

    <!-- =============================================== -->
    <!-- SCRIPTS -->
    <!-- =============================================== -->
    
    <!-- jQuery (จำเป็นสำหรับ Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap 5 Bundle (รวม Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS (สำหรับ Dropdown ค้นหาได้) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- SweetAlert2 (สำหรับ Popup แจ้งเตือนสวยๆ) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /**
         * 1. Sidebar Toggle Logic (สำหรับมือถือ)
         */
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        }

        // ปิดเมนูอัตโนมัติเมื่อขยายจอเป็น Desktop (ป้องกัน Layout เพี้ยน)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.sidebar-overlay');
                if(sidebar) sidebar.classList.remove('show');
                if(overlay) overlay.classList.remove('active');
            }
        });

        /**
         * 2. Initialize Plugins (ทำงานเมื่อโหลดหน้าเสร็จ)
         */
        $(document).ready(function() {
            // ตั้งค่าเริ่มต้นให้ Select2 ทุกตัวทำงานร่วมกับ Bootstrap Modal ได้ดีขึ้น
            $.fn.select2.defaults.set( "theme", "bootstrap-5" );
        });

        /**
         * 3. Global Notification System (Flash Messages)
         * รับค่าจาก PHP Session มาแสดงผล
         */
        <?php if (isset($_SESSION['swal_success'])): ?>
            Swal.fire({
                icon: 'success',
                title: '<?php echo $_SESSION['swal_success']['title']; ?>',
                text: '<?php echo $_SESSION['swal_success']['text']; ?>',
                timer: 2000, // ปิดเองใน 2 วินาที
                showConfirmButton: false,
                backdrop: `rgba(0,0,123,0.1)`
            });
            <?php unset($_SESSION['swal_success']); // เคลียร์ค่าทิ้ง ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['swal_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: '<?php echo $_SESSION['swal_error']['title']; ?>',
                text: '<?php echo $_SESSION['swal_error']['text']; ?>',
                confirmButtonText: 'รับทราบ',
                confirmButtonColor: '#d33'
            });
            <?php unset($_SESSION['swal_error']); ?>
        <?php endif; ?>

    </script>
</body>
</html>