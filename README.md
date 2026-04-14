# Freelance Services Marketplace

A database-driven web application that connects clients with freelancers offering services in different categories such as web development, graphic design, writing, and digital marketing. The project was built using **PHP, HTML, CSS, and MySQL** as required in the course specification. :contentReference[oaicite:0]{index=0}

## Project Overview

This project simulates a freelance services platform where:
- users can register and log in as **Client** or **Freelancer**
- freelancers can create, edit, and manage service listings
- clients can browse services, add them to cart, and place orders
- both clients and freelancers can manage orders and revisions

The application follows a multi-page structure and uses server-side processing with PHP sessions and MySQL database integration. :contentReference[oaicite:1]{index=1}

## Technologies Used

- HTML5
- CSS3
- PHP
- MySQL
- PDO for database connection and prepared statements

The project requirements explicitly restrict implementation to HTML5, CSS, PHP, and MySQL, with frameworks prohibited. :contentReference[oaicite:2]{index=2}

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

These use cases are listed in the requirements specification. :contentReference[oaicite:3]{index=3} :contentReference[oaicite:4]{index=4}

## Database

**Database name:** `freelance_marketplace` :contentReference[oaicite:5]{index=5}

The database contains the main tables:
- `users`
- `categories`
- `services`
- `orders`
- `revision_requests`
- `file_attachments` :contentReference[oaicite:6]{index=6} :contentReference[oaicite:7]{index=7}

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

<img width="935" height="421" alt="2" src="https://github.com/user-attachments/assets/9e3d5f1a-19d9-49ff-aefb-22cd2209d918" />
<img width="959" height="440" alt="1" src="https://github.com/user-attachments/assets/eb491f77-2e19-466b-8c95-29843ad15dee" />
<img width="956" height="443" alt="4" src="https://github.com/user-attachments/assets/8f580aea-b669-4817-9126-19b2340289ce" />











