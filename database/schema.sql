CREATE DATABASE IF NOT EXISTS academic_planner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academic_planner;
-- Aman diimpor berulang: tabel yang sudah ada dilewati, data TIDAK dihapus.
-- Untuk mengulang dari nol, jalankan database/reset.sql lalu import file ini lagi.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  kode_matkul VARCHAR(20),
  nama_matkul VARCHAR(150) NOT NULL,
  dosen VARCHAR(100),
  hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  jam TIME NOT NULL,
  ruangan VARCHAR(50),
  tanggal_mulai DATE NOT NULL,            -- awal semester, dasar generate 16 pertemuan
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_sessions (            -- hasil auto-generate (16 baris per matkul)
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  tanggal DATE NOT NULL,
  pertemuan_ke TINYINT NOT NULL,
  UNIQUE KEY uq_course_pertemuan (course_id, pertemuan_ke),
  INDEX idx_tanggal (tanggal),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lecture_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL UNIQUE,
  isi_catatan TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES course_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  judul_tugas VARCHAR(200) NOT NULL,
  deskripsi TEXT,
  deadline DATE NOT NULL,
  prioritas ENUM('Rendah','Sedang','Tinggi') DEFAULT 'Sedang',
  status ENUM('Belum Selesai','Selesai') DEFAULT 'Belum Selesai',
  INDEX idx_deadline (deadline),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- User demo (password: "password"). Hanya dibuat jika belum ada, jadi aman diimpor berulang.
INSERT INTO users (nama, email, password)
SELECT 'Mahasiswa Demo', 'demo@kampus.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'demo@kampus.ac.id');
