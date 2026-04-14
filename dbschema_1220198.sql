
CREATE DATABASE IF NOT EXISTS freelance_marketplace
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE freelance_marketplace;


CREATE TABLE users (
    user_id VARCHAR(10) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(10) NOT NULL,
    country VARCHAR(50) NOT NULL,
    city VARCHAR(50) NOT NULL,
    role ENUM('Client', 'Freelancer') NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    profile_photo VARCHAR(255),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE users ADD bio TEXT NULL;





CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    Programming VARCHAR(100),
    Design VARCHAR(100),
    Writing VARCHAR(100),
    Marketing VARCHAR(100)
);

INSERT INTO categories (category_name) VALUES
('Web Development'),
('Graphic Design'),
('Writing & Translation'),
('Digital Marketing'),
('Video & Animation');


CREATE TABLE services (
    service_id VARCHAR(10) PRIMARY KEY,
    freelancer_id VARCHAR(10) NOT NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    delivery_time INT NOT NULL,
    revisions_included INT NOT NULL,
    image_1 VARCHAR(255) NOT NULL,
    image_2 VARCHAR(255),
    image_3 VARCHAR(255),
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    featured_status ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE orders (
    order_id VARCHAR(10) PRIMARY KEY,
    client_id VARCHAR(10) NOT NULL,
    freelancer_id VARCHAR(10) NOT NULL,
    service_id VARCHAR(10) NOT NULL,
    service_title VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    delivery_time INT NOT NULL,
    revisions_included INT NOT NULL,
    requirements TEXT NOT NULL,
    deliverable_notes TEXT,
    status ENUM(
        'Pending',
        'In Progress',
        'Delivered',
        'Completed',
        'Revision Requested',
        'Cancelled'
    ) NOT NULL DEFAULT 'Pending',
    payment_method VARCHAR(50) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_delivery DATE NOT NULL,
    completion_date TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT
);


CREATE TABLE revision_requests (
    revision_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(10) NOT NULL,
    revision_notes TEXT NOT NULL,
    revision_file VARCHAR(255),
    request_status ENUM('Pending', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_date TIMESTAMP NULL,
    freelancer_response TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

  

CREATE TABLE file_attachments (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(10) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type ENUM('requirement', 'deliverable', 'revision') NOT NULL,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);
----------------------------------------------------------------------------------------


 
INSERT INTO users (
  user_id, first_name, last_name, email, password,
  phone, country, city, role, status, profile_photo
) VALUES
('2000000001','Ahmad','Saleh','ahmad.saleh@gmail.com',
 '$2y$10$demoHashAhmadSaleh0000000000000000000000000000000000',
 '0591111111','Palestine','Ramallah','Freelancer','Active', '/uploads/profile-photo/ahmad.jpg'),

('2000000002','Lina','Khaled','lina.khaled@gmail.com',
 '$2y$10$demoHashLinaKhaled0000000000000000000000000000000000',
 '0592222222','Palestine','Nablus','Freelancer','Active', '/uploads/profile-photo/lina.jpg'),

('2000000003','Omar','Hassan','omar.hassan@gmail.com',
 '$2y$10$demoHashOmarHassan0000000000000000000000000000000000',
 '0593333333','Palestine','Hebron','Client','Active', '/uploads/profile-photo/omar.jpg'),

('2000000004','Sara','Naser','sara.naser@gmail.com',
 '$2y$10$demoHashSaraNaser0000000000000000000000000000000000',
 '0594444444','Palestine','Jenin','Client','Active', '/uploads/profile-photo/sara.jpg'),

('2000000005','Yousef','Ali','yousef.ali@gmail.com',
 '$2y$10$demoHashYousefAli0000000000000000000000000000000000',
 '0595555555','Palestine','Tulkarm','Freelancer','Active', '/uploads/profile-photo/yousef.jpg'),

('2000000006','Mariam','Awad','mariam.awad@gmail.com',
 '$2y$10$demoHashMariamAwad000000000000000000000000000000000',
 '0596666666','Palestine','Bethlehem','Client','Active', '/uploads/profile-photo/mariam.jpg');
INSERT INTO services (
  service_id, freelancer_id, title, category, subcategory, description,
  price, delivery_time, revisions_included,
  image_1, image_2, image_3, status, featured_status
) VALUES
('9000000011','2000000001',
 'Responsive Website Landing Page','Web Development','Front-End',
 'I will build a clean responsive landing page (HTML/CSS/JS) optimized for mobile and desktop.',
 180.00,4,2,
 '/uploads/services/web_lp_main.jpg',NULL,NULL,'Active','No'),

('9000000012','2000000001',
 'Fix Bugs in PHP / MySQL Project','Web Development','Back-End',
 'I will fix bugs, errors, and database issues in your PHP/MySQL project and improve performance.',
 120.00,2,3,
 '/uploads/services/php_fix_main.jpg',NULL,NULL,'Active','Yes'),

('9000000013','2000000002',
 'Modern Logo Design + Brand Kit','Graphic Design','Logo Design',
 'I will design a modern logo with color palette and basic brand guidelines.',
 150.00,3,2,
 '/uploads/services/logo_main.jpg','/uploads/services/logo_extra.jpg',NULL,'Active','No'),

('9000000014','2000000002',
 'Social Media Post Design Pack','Graphic Design','Social Media Design',
 'I will create 10 social media posts (Instagram/Facebook) with consistent branding.',
 90.00,2,2,
 '/uploads/services/social_posts_main.jpg',NULL,NULL,'Active','No'),

('9000000015','2000000005',
 'SEO Blog Article (1000 words)','Writing & Translation','Content Writing',
 'I will write an SEO-friendly 1000-word blog post with keywords and proper headings.',
 60.00,2,999,
 '/uploads/services/seo_article_main.jpg',NULL,NULL,'Active','No'),

('9000000016','2000000005',
 'Arabic to English Translation','Writing & Translation','Translation',
 'I will translate Arabic to English with accurate meaning and natural wording.',
 40.00,1,2,
 '/uploads/services/translation_main.jpg',NULL,NULL,'Active','Yes'),

('9000000017','2000000001',
 'Database Design (ERD + SQL)','Web Development','Database',
 'I will design your database schema and provide ERD and SQL scripts with relationships.',
 200.00,4,2,
 '/uploads/services/db_design_main.jpg',NULL,NULL,'Active','No'),

('9000000018','2000000002',
 'Video Editing for Reels/TikTok','Video & Animation','Video Editing',
 'I will edit short videos with cuts, subtitles, and smooth transitions for social media.',
 110.00,3,2,
 '/uploads/services/video_edit_main.jpg',NULL,NULL,'Active','No'),

('9000000019','2000000005',
 'Social Media Marketing Plan','Digital Marketing','Social Media',
 'I will create a 2-week social media content plan with posting schedule and ideas.',
 130.00,3,2,
 '/uploads/services/marketing_plan_main.jpg',NULL,NULL,'Active','No'),

('9000000020','2000000001',
 'WordPress Setup + Basic Customization','Web Development','WordPress',
 'I will install WordPress, setup theme, and customize basic pages (Home, About, Contact).',
 160.00,3,2,
 '/uploads/services/wp_setup_main.jpg',NULL,NULL,'Active','No');

UPDATE users
SET password = '$2y$10$6MJsG35anLMstkufeG74leVlpptUu.pfT4CMYCqgCY4GUcMylR9Ma'
WHERE user_id IN ('2000000001','2000000002','2000000003','2000000004','2000000005','2000000006');
