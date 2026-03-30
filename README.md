# 🏥 PhilHealth Online Appointment and Queue Management System

## 📌 Overview

This project is a **Web-Based Appointment with Queue Management System** developed for the PhilHealth Local Health Insurance Office (LHIO). It aims to improve service efficiency, reduce waiting time, and enhance user experience through digital queueing and chatbot assistance.

---

## 🎯 Objectives

* To streamline client appointments and queue processing
* To implement a **First-Come, First-Served (FCFS)** scheduling algorithm
* To provide automated assistance using an AI chatbot
* To improve service delivery and reduce overcrowding

---

## ⚙️ Features

* 📅 Online Appointment Booking
* 🔢 Automatic Queue Number Generation (FCFS)
* 👨‍💼 Staff Dashboard for Queue Management
* 👤 User Registration and Login System
* 💬 AI Chatbot for Frequently Asked Questions
* 📊 Real-time Queue Monitoring
* 📁 Downloadable PhilHealth Forms (PMRF)

---

## 🛠️ Technologies Used

* **Frontend:** HTML, CSS, JavaScript
* **Backend:** PHP
* **Database:** MySQL
* **Server:** XAMPP
* **AI Integration:** OpenAI API (for chatbot)

---

## 🧠 System Architecture

The system follows a **Web-Based Client-Server Architecture**:

* Users interact through a web interface
* The server processes requests and manages queue logic
* The chatbot provides automated responses based on trained data

---

## 🚀 How to Run the System

1. Clone the repository:

   ```bash
   git clone https://github.com/DarelSeviola/Philhealth_online_system.git
   ```

2. Move the project folder to:

   ```
   C:\xampp\htdocs\
   ```

3. Start XAMPP:

   * Apache ✔️
   * MySQL ✔️

4. Import the database:

   * Open **phpMyAdmin**
   * Create a database (e.g., `philhealth_queue`)
   * Import the file:

     ```
     sql/philhealth_queue.sql
     ```

5. Configure database connection:

   * Open:

     ```
     config/db.php
     ```
   * Update credentials if needed

6. Run the system:

   ```
   http://localhost/philhealth_queue
   ```

---

## 🔐 Security Notes

* Sensitive files like `.env` are excluded using `.gitignore`
* API keys are stored securely and not exposed in the repository

---

## 📊 Evaluation Metrics

* Functional Test Case Success Rate
* System Usability Scale (SUS)

---

## 👨‍🎓 Researchers

* Darel Ann Stefanny Seviola
* Kathy Mae Abadingo
* Josiah Jay Batiola
* Bachelor of Science in Computer Science
* North Eastern Mindanao State University

---

## 📌 Disclaimer

This system is developed for **academic purposes only** and is not an official PhilHealth system.

---
