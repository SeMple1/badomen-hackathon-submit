# Badomen Event Platform

Badomen คือเว็บแพลตฟอร์มสำหรับค้นหา สมัครเข้าร่วม และจัดการกิจกรรม ถูกพัฒนาสำหรับงาน Hackathon โดยเน้นให้ผู้ใช้สามารถค้นหากิจกรรมที่สนใจ สมัครเข้าร่วม ติดตามสถานะตั๋ว และให้ผู้จัดกิจกรรมสามารถดูข้อมูลภาพรวมของผู้เข้าร่วมได้ในระบบเดียว

## Project Overview

ระบบนี้ออกแบบมาเพื่อแก้ปัญหาการจัดการกิจกรรมที่กระจัดกระจาย เช่น การประกาศกิจกรรม การสมัครเข้าร่วม การตรวจสอบสถานะ การยืนยันสิทธิ์ และการดูข้อมูลผู้เข้าร่วม โดยรวมทุกขั้นตอนไว้ในเว็บเดียวที่ใช้งานง่ายและเหมาะกับกิจกรรมของมหาวิทยาลัยหรือองค์กรขนาดเล็กถึงกลาง

## Main Features

### For Users

* สมัครสมาชิกและเข้าสู่ระบบ
* ค้นหากิจกรรมจากชื่อกิจกรรมหรือสถานที่
* ดูรายละเอียดกิจกรรม วันเวลา สถานที่ และจำนวนผู้เข้าร่วม
* สมัครเข้าร่วมกิจกรรม
* ตรวจสอบสถานะการสมัคร
* ดูตั๋วกิจกรรมของตัวเอง
* แก้ไขข้อมูลโปรไฟล์
* บันทึกกิจกรรมที่สนใจ
* รองรับระบบ OTP / Ticket / Mock Payment สำหรับจำลอง Flow การเข้าร่วมกิจกรรม

### For Organizers

* สร้างกิจกรรมใหม่
* จัดการข้อมูลกิจกรรม
* ดูจำนวนผู้สมัครเข้าร่วม
* ตรวจสอบสถานะผู้เข้าร่วม
* ดูข้อมูลภาพรวมผ่าน Dashboard
* รองรับข้อมูลเชิงสถิติ เช่น เพศ อายุ และอาชีพของผู้เข้าร่วม

### Additional System Concepts

* ระบบตั๋วกิจกรรม
* ระบบรายการโปรด
* ระบบแจ้งเตือน
* ระบบคะแนนสะสม
* ระบบคูปอง
* ระบบ Feedback
* ระบบ Refund
* ระบบ Mock Payment สำหรับจำลองการชำระเงิน

## Tech Stack

* PHP
* MySQL
* HTML
* CSS
* JavaScript
* Apache / XAMPP
* phpMyAdmin

## Project Structure

```text
badomen/
├── assets/                 # รูปภาพ โลโก้ และไฟล์ static
├── includes/               # ไฟล์ config, database, security, router
├── routes/                 # ไฟล์ควบคุม route แต่ละหน้า
├── templates/              # ไฟล์หน้าเว็บและ partial UI
├── uploads/                # โฟลเดอร์สำหรับรูปภาพที่ผู้ใช้อัปโหลด
├── database.sql            # โครงสร้างฐานข้อมูล
├── seed-demo.sql           # ข้อมูลตัวอย่างสำหรับทดสอบ
├── README.md
└── .gitignore
```

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/USERNAME/REPOSITORY_NAME.git
```

จากนั้นเข้าไปที่โฟลเดอร์โปรเจกต์

```bash
cd REPOSITORY_NAME
```

### 2. Move Project to Web Server

ถ้าใช้ XAMPP ให้นำโฟลเดอร์โปรเจกต์ไปไว้ที่

```text
C:/xampp/htdocs/
```

ตัวอย่าง:

```text
C:/xampp/htdocs/badomen
```

### 3. Create Database

เปิด phpMyAdmin แล้วสร้างฐานข้อมูลใหม่ เช่น

```sql
CREATE DATABASE badomen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Import Database

นำเข้าไฟล์ SQL ตามลำดับ

```text
database.sql
seed-demo.sql
```

ถ้ามีเฉพาะ `database.sql` ให้ import เฉพาะไฟล์นั้นก่อน แล้วค่อยเพิ่มข้อมูลกิจกรรมเองผ่านระบบหรือ phpMyAdmin

### 5. Configure Database Connection

สร้างไฟล์ config สำหรับเชื่อมต่อฐานข้อมูล เช่น `config.php` หรือไฟล์ที่โปรเจกต์กำหนดไว้

ตัวอย่าง:

```php
<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'badomen');
define('DB_USER', 'root');
define('DB_PASS', '');
```

> หมายเหตุ: ห้ามอัปโหลดไฟล์ config จริงที่มีรหัสผ่านขึ้น GitHub public repository
> ควรใช้ไฟล์ตัวอย่าง เช่น `config.example.php` แทน

### 6. Run Project

เปิดผ่าน Browser

```text
http://localhost/badomen
```

หรือถ้าตั้งค่า Virtual Host / Router แล้ว สามารถเปิดตาม path ที่กำหนดไว้ เช่น

```text
http://localhost/home_in
```

## Demo Account

> แก้ข้อมูลส่วนนี้ให้ตรงกับบัญชีที่มีในฐานข้อมูลจริง

### User Account

```text
Email: demo@example.com
Password: 12345678
```

### Organizer Account

```text
Email: organizer@example.com
Password: 12345678
```

## Important Pages

| Page               | Description                   |
| ------------------ | ----------------------------- |
| `/`                | หน้าแรกสำหรับผู้ใช้ทั่วไป     |
| `/login`           | หน้าเข้าสู่ระบบ               |
| `/register`        | หน้าสมัครสมาชิก               |
| `/home_in`         | หน้าแรกหลังเข้าสู่ระบบ        |
| `/profile`         | หน้าโปรไฟล์ผู้ใช้             |
| `/edit_profile`    | หน้าแก้ไขโปรไฟล์              |
| `/create_activity` | หน้าสร้างกิจกรรม              |
| `/dashboard`       | Dashboard สำหรับผู้จัดกิจกรรม |
| `/join_activity`   | หน้ากิจกรรมที่ผู้ใช้เข้าร่วม  |

## Database Overview

ตารางหลักที่ใช้งานในระบบ

| Table           | Purpose                            |
| --------------- | ---------------------------------- |
| `users`         | เก็บข้อมูลผู้ใช้                   |
| `events`        | เก็บข้อมูลกิจกรรม                  |
| `registrations` | เก็บข้อมูลการสมัครเข้าร่วมกิจกรรม  |
| `event_images`  | เก็บรูปภาพกิจกรรม                  |
| `event_tickets` | เก็บข้อมูลตั๋วกิจกรรม              |
| `favorites`     | เก็บกิจกรรมที่ผู้ใช้บันทึกไว้      |
| `notifications` | เก็บข้อมูลการแจ้งเตือน             |
| `coupons`       | เก็บข้อมูลคูปอง                    |
| `points`        | เก็บคะแนนสะสมของผู้ใช้             |
| `feedback`      | เก็บความคิดเห็นหลังเข้าร่วมกิจกรรม |
| `refunds`       | เก็บข้อมูลการขอคืนเงิน             |

## Security Notes

เพื่อความปลอดภัย ไม่ควรอัปโหลดไฟล์เหล่านี้ขึ้น public repository

```text
.env
config.php
database.php
ไฟล์ backup ฐานข้อมูลจริง
ไฟล์ที่มีรหัสผ่าน
API key
FTP credential
ข้อมูลผู้ใช้จริง
```

ควรใช้ `.gitignore` เพื่อป้องกันไฟล์สำคัญหลุดขึ้น GitHub

ตัวอย่าง `.gitignore`

```gitignore
.env
.env.*
!.env.example

config.php
config.local.php
database.php

uploads/*
!uploads/.gitkeep

logs/
cache/
*.log

*.zip
*.rar
*.7z
*.sql.gz

.DS_Store
Thumbs.db
```

## Current Status

ระบบอยู่ในเวอร์ชันสำหรับนำเสนอในงาน Hackathon โดยมีฟีเจอร์หลักสำหรับการค้นหากิจกรรม สมัครเข้าร่วมกิจกรรม ดูตั๋ว แก้ไขโปรไฟล์ และดู Dashboard ของผู้จัดกิจกรรม

บางฟีเจอร์ เช่น ระบบชำระเงินจริง ระบบแจ้งเตือนจริง หรือระบบ OTP จริง อาจอยู่ในรูปแบบ Mock-up เพื่อแสดงแนวคิดและ Flow การทำงานของระบบ

## Future Improvements

* เชื่อมต่อระบบชำระเงินจริง
* เพิ่มระบบส่ง Email Notification
* เพิ่มระบบ OTP จริง
* เพิ่มระบบ QR Code สำหรับ Check-in
* เพิ่มระบบจัดการรอบกิจกรรมหลายวัน
* เพิ่มระบบ Export รายชื่อผู้เข้าร่วม
* เพิ่มระบบวิเคราะห์ข้อมูลกิจกรรมเชิงลึก
* ปรับปรุง Performance และ Lighthouse Score
* เพิ่มระบบ Role Permission สำหรับ User, Organizer และ Admin

## Team

พัฒนาโดยทีม Hackathon IT MSU

```text
Project: Badomen Event Platform
Event: Hackathon IT MSU
```

## License

This project is created for educational and hackathon purposes.
