<?php
// รหัสผ่านที่เราต้องการตั้ง
$password = 'password1234'; 

// เข้ารหัสผ่านด้วย BCRYPT (เป็น default และปลอดภัย)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// แสดงผลรหัสผ่านที่เข้ารหัสแล้ว
echo $hashedPassword; 

// ตัวอย่างผลลัพธ์ที่ได้ (จะเปลี่ยนไปทุกครั้งที่รัน):
// $2y$10$vF1IMiM2Fq9yZ3nE5tF.y.J2O7U3kP.e0sR9gY5aL3eX8wZ9iT1wG
?>