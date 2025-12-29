# Employee Management System (Single File Project)

A simple **Employee Management System** developed using **PHP, HTML, CSS, and JavaScript**, where **all functionality is implemented inside a single file (`employee.php`)**. This project is ideal for mini-projects, academic submissions, and beginners learning full-stack web development with PHP.

---

## 📌 Project Overview

This project demonstrates how backend logic, frontend design, styling, and client-side validation can be combined in **one PHP file**. It performs basic CRUD (Create, Read, Update, Delete) operations on employee records using a database connection.

---

## ✨ Features

* Add new employee records
* Display employee details
* Update employee information
* Delete employee records
* Form validation using JavaScript
* Server-side processing using PHP
* Clean and simple user interface
* All code in **one file: `employee.php`**

---

## 🛠️ Technologies Used

* **PHP** – Backend logic and database operations
* **HTML** – Structure of the web page
* **CSS** – Styling and layout
* **JavaScript** – Client-side validation and interactivity
* **MySQL** – Database
* **Apache Server** – XAMPP / WAMP

---

## 📂 Project Structure

```
/project-folder
│── employee.php   (PHP + HTML + CSS + JavaScript)
│── README.md
```

---

## ⚙️ Installation & Setup

1. Install **XAMPP** or **WAMP**.
2. Place `employee.php` inside:

   * `htdocs` (XAMPP) or
   * `www` (WAMP)
3. Start **Apache** and **MySQL**.
4. Create a database in **phpMyAdmin**.
5. Create an `employee` table.
6. Update database credentials inside `employee.php`.
7. Open your browser and run:

```
http://localhost/employee.php
```

---

## 🧾 Sample Database Schema

```sql
CREATE TABLE employee (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    department VARCHAR(100),
    salary DECIMAL(10,2)
);
```

---

## 🎯 Learning Outcomes

* Understanding PHP CRUD operations
* Combining frontend and backend in one file
* Form handling and validation
* Database connectivity with MySQL
* Practical exposure to full-stack development

---

## 🚀 Future Enhancements

* Split code into MVC structure
* Add authentication (Admin login)
* Search and filter functionality
* Pagination
* Improve UI with Bootstrap

---

## 👩‍💻 Author

**Sakshi Patil**

* GitHub: [https://github.com/sakshipatil-25](https://github.com/sakshipatil-25)
* LinkedIn: [https://www.linkedin.com/in/sakshipopatraopatil/](https://www.linkedin.com/in/sakshipopatraopatil/)

---

## 📄 License

This project is created for **educational purposes** and is free to use and modify.

---

⭐ *If you like this project, don’t forget to give it a star!*
