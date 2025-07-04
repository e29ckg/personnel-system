# ระบบจัดการข้อมูลบุคลากร (Personnel Management System)

ระบบสำหรับจัดการข้อมูลบุคลากรแบบครบวงจร ถูกออกแบบมาเพื่อความปลอดภัยและประสิทธิภาพในการจัดการข้อมูล พัฒนาด้วย PHP และ Vue.js โดยแบ่งการเข้าถึงเป็น 2 ระดับคือ: **ผู้ดูแลระบบ (Admin)** และ **สมาชิก (Member)**

ระบบนี้ใช้สถาปัตยกรรมที่ทันสมัยโดยแยกการตั้งค่า (Configuration) ออกจากโค้ดผ่านไฟล์ `.env` และจัดการส่วนเสริม (Dependencies) ด้วย Composer

---

## คุณสมบัติหลัก (Features)

### ด้านความปลอดภัย (Security)

- **Role-Based Access Control (RBAC):** แบ่งสิทธิ์การเข้าถึงระหว่าง `Admin` และ `Member`
- **Backend-Driven Authentication:** การตรวจสอบสิทธิ์ทั้งหมดทำผ่าน PHP Session ที่ฝั่งเซิร์ฟเวอร์
- **Secure Configuration:** จัดการข้อมูลลับ (Database Credentials, Secret Keys) ผ่านไฟล์ `.env` ไม่เก็บไว้ในโค้ดโดยตรง
- **Encryption at Rest:** เข้ารหัสเลขประจำตัวประชาชนด้วย `OpenSSL (AES-256-CBC)` ก่อนบันทึกลงฐานข้อมูล
- **Blind Indexing for Search:** ใช้เทคนิค Hashing (SHA-256) กับเลขบัตรประชาชนเพื่อทำให้สามารถค้นหาข้อมูลที่เข้ารหัสได้อย่างปลอดภัย (เฉพาะแบบ Exact Match)
- **Prepared Statements (PDO):** ป้องกันการโจมตีแบบ SQL Injection

### แผงควบคุมผู้ดูแลระบบ (Admin Panel)

- **Tabbed Interface:** แยกส่วน "จัดการบุคลากร" และ "จัดการผู้ใช้งาน" เพื่อความสะดวก
- **Server-Side Paginated Tables:** ตารางข้อมูลทั้งหมดใช้การแบ่งหน้าจากฝั่ง Backend ทำให้รองรับข้อมูลจำนวนมากได้อย่างรวดเร็ว
- **Vue-Native Table Controls:** สร้างระบบ **ค้นหา (Debounced), เรียงข้อมูล, และแบ่งหน้า** ด้วย Vue.js ล้วนๆ
- **User & Personnel Management (CRUD):** จัดการข้อมูลบุคลากรและผู้ใช้งานได้อย่างครบวงจร
- **Image Uploads:** รองรับการอัปโหลด, แสดงผล, และลบรูปโปรไฟล์
- **Input Mask:** บังคับรูปแบบการกรอกเลขบัตรประชาชนอัตโนมัติ

### หน้าสำหรับสมาชิก (Member Area)

- **Protected Search:** เฉพาะสมาชิกที่ Login แล้วเท่านั้นที่สามารถเข้าถึงหน้าค้นหาได้
- **Detailed View Modal:** แสดงรายละเอียดข้อมูลของบุคลากรใน Modal

---

## เทคโนโลยีที่ใช้ (Technology Stack)

- **Backend:**
  - PHP 8+
  - MySQL (MariaDB)
  - PDO
- **Dependency Management:**
  - **Composer**
- **Frontend:**
  - HTML5, CSS3, JavaScript (ES6), **Vue.js 3 (CDN)**
- **Styling:**
  - Bootstrap 5
  - Google Fonts (Sarabun)
- **Security:**
  - OpenSSL
  - phpdotenv (สำหรับจัดการ Environment Variables)
- **Libraries:**
  - Axios
  - SweetAlert2

---

## การติดตั้งและใช้งาน (Getting Started)

### สิ่งที่ต้องมี (Prerequisites)

- โปรแกรมจำลองเซิร์ฟเวอร์ เช่น [XAMPP](https://www.apachefriends.org/index.html) (ต้องมี Apache, MySQL, PHP 8+)
- [Composer](https://getcomposer.org/) ติดตั้งบนเครื่องของคุณ

### ขั้นตอนการติดตั้ง

1.  **Clone a Repository:**

    ```sh
    git clone https://github.com/e29ckg/personnel-system.git
    cd personnel-system
    ```

    หรือดาวน์โหลดโปรเจกต์เป็นไฟล์ ZIP แล้วแตกไฟล์ไว้ในโฟลเดอร์ `htdocs` ของ XAMPP

2.  **ติดตั้ง Dependencies:**
    เปิด Command Prompt หรือ Terminal ที่โฟลเดอร์โปรเจกต์ แล้วรันคำสั่งนี้เพื่อติดตั้ง Library ที่จำเป็น (เช่น `phpdotenv`):

    ```sh
    composer install
    ```

    คำสั่งนี้จะอ่านไฟล์ `composer.json` และ `composer.lock` แล้วดาวน์โหลดทุกอย่างที่จำเป็นมาไว้ในโฟลเดอร์ `vendor/`

3.  **สร้างไฟล์ Environment (`.env`):**

    - ที่โฟลเดอร์หลักของโปรเจกต์ **สร้างไฟล์ใหม่** แล้วตั้งชื่อว่า **`.env`**
    - คัดลอกเนื้อหาข้างล่างนี้ไปใส่ในไฟล์ `.env` แล้วแก้ไขค่าให้ถูกต้องตามการตั้งค่าของคุณ

    ```env
    # Database Configuration
    DB_HOST=localhost
    DB_NAME=personnel_db
    DB_USER=root
    DB_PASS=

    # Encryption Configuration
    ENCRYPTION_KEY="Your-Super-Secret-32-Byte-Key-Here"
    ENCRYPTION_CIPHER=AES-256-CBC
    ```

4.  **ตั้งค่าฐานข้อมูล:**

    - เปิด **phpMyAdmin** และสร้างฐานข้อมูลใหม่ (ต้องตรงกับ `DB_NAME` ในไฟล์ `.env`)
    - **สร้างตารางหลัก:** ไปที่แท็บ `SQL` แล้วรันคำสั่ง SQL จากไฟล์ `database_schema.sql` (หรือคัดลอกคำสั่ง `CREATE TABLE` จาก README เวอร์ชันก่อนหน้า) เพื่อสร้างตาราง `users` และ `personnel`, `thai_amphures`, `thai_amphures`, `thai_tambons`

5.  **สร้างโฟลเดอร์ `uploads`:**

    - ในโฟลเดอร์หลักของโปรเจกต์ ให้สร้างโฟลเดอร์ใหม่ชื่อ `uploads` สำหรับเก็บรูปภาพ

6.  **กำหนดสิทธิ์ Admin:**

    - ในตาราง `users` ให้แก้ไขข้อมูลผู้ใช้หลักของคุณ แล้วเปลี่ยนค่าในคอลัมน์ `role` ให้เป็น `admin`

7.  **เข้าใช้งาน:**
    - ไปที่ `http://localhost/personnel-system/`
