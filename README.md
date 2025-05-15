# 🚀 TaskFlow API

This repository contains the backend RESTful API for [TaskFlow](https://taskflowapp.net/), a minimalist productivity web app focused on task management and user productivity.

Built in pure PHP with an object-oriented MVC architecture, the API handles secure JWT authentication, rate limiting with Redis, and communicates with a MySQL database for reliable data storage.

> For the full client-side application, including the user interface and detailed documentation, please visit the [TaskFlow client repository](https://github.com/AngelValentino/TaskFlow).


## 🛡️ Features and Security

- Secure JWT-based authentication and token refresh with robust token management  
- Rate limiting powered by Redis to prevent abuse and mitigate brute-force attacks  
- CORS support with IP and device ID validation, plus strict input sanitization to enhance security  
- Modular, maintainable MVC architecture with Composer autoloader for professional, clean structure  
- MySQL database integration using prepared statements for SQL injection prevention, hosted separately from the API in an isolated environment  
- Thorough user input validation and JSON escaping to prevent XSS and other injection attacks  
- Deployed on a hardened Linux server with SSH-only access, Fail2Ban, and strict file permissions. It uses HTTPS with an A+ SSL Labs rating for secure communication. Log rotation is configured for API logs, and Apache serves the API from the public folder with `.htaccess` for URL rewriting and added security.

Despite best efforts and adherence to industry best practices, no web application can guarantee 100% security due to inherent platform limitations and constantly evolving threats; ongoing vigilance and improvements remain essential.


## 🛫 Getting Started

Please refer to the [client repository](https://github.com/AngelValentino/TaskFlow) for detailed setup instructions and usage examples.