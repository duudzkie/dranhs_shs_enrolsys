# DRANHS Portal

A dynamic web portal for Daniel R. Aguinaldo National High School (DRANHS) built with PHP, HTML, CSS, and JavaScript.

## Features

- **Main Portal**: Landing page with navigation to various sections
- **Student Portal**: Enrollment system (EMS2)
- **Admin Panel**: Edit page content dynamically
- **Responsive Design**: Works on desktop and mobile

## Local Development

1. Install XAMPP or similar PHP server
2. Clone this repository to `htdocs` folder
3. Start Apache and MySQL
4. Access at `http://localhost/dranhs-portal`

## Deployment on Railway.app

1. Go to [Railway.app](https://railway.app)
2. Connect your GitHub account
3. Select this repository
4. Railway will auto-detect PHP and deploy
5. Set environment variables if needed (database, etc.)

## Database Setup

For full functionality, set up MySQL database:
- Import `EMS2/setup_users.sql`
- Configure database connection in PHP files

## File Structure

- `index.php` - Main landing page
- `EMS2/` - Student enrollment system
- `components/` - Shared UI components
- `main_*.php` - Section pages
- `page_settings.json` - Editable content storage

## Technologies

- PHP 7+
- MySQL
- HTML5/CSS3
- JavaScript
- Tailwind CSS (via CDN)