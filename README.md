# Freelance Services Marketplace

A database-driven web application that connects clients with freelancers offering services in different categories such as web development, graphic design, writing, and digital marketing. The project was built using **PHP, HTML, CSS, and MySQL** as required in the course specification.

## Project Overview

This project simulates a freelance services platform where:
- users can register and log in as **Client** or **Freelancer**
- freelancers can create, edit, and manage service listings
- clients can browse services, add them to cart, and place orders
- both clients and freelancers can manage orders and revisions

The application follows a multi-page structure and uses server-side processing with PHP sessions and MySQL database integration.

## Technologies Used

- HTML5
- CSS3
- PHP
- MySQL
- PDO for database connection and prepared statements

The project requirements explicitly restrict implementation to HTML5, CSS, PHP, and MySQL, with frameworks prohibited.

## Main Features

- User registration and login
- Role-based access control for Clients and Freelancers
- Profile management
- Create and edit service listings
- Browse and search services
- Service details page
- Add to cart and manage shopping cart
- Multi-step checkout
- Order management
- Revision request handling
- File attachments for requirements, deliverables, and revisions

These use cases are listed in the requirements specification.

## Database

**Database name:** `freelance_marketplace`

The database contains the main tables:
- `users`
- `categories`
- `services`
- `orders`
- `revision_requests`
- `file_attachments`

## Project Structure

```text
std1220198/
├── assets/
├── css/
├── includes/
├── uploads/
├── home.php
├── login.php
├── register.php
├── browse-services.php
├── service-detail.php
├── cart.php
├── checkout.php
├── dashboard.php
├── my-orders.php
├── my-services.php
├── create-service.php
├── edit-service.php
├── profile.php
├── dbschema_1220198.sql
└── index.html

<img width="678" height="310" alt="image" src="https://github.com/user-attachments/assets/d47d0c34-2a45-4b65-8aed-89a28f1d42c2" />


<img width="959" height="440" alt="1" src="https://github.com/user-attachments/assets/f3b29408-2445-436a-8b8a-0bb2109b17ab" />

