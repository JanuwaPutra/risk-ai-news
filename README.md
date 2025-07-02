# Potential Risk Intelligence - Media Monitoring

This application analyzes news articles and evaluates the risk level associated with specific people mentioned in the news. It uses AI to generate risk assessments and recommendations based on the statements and actions of individuals in the news.

## Features

- Upload Excel files containing person data (name, position)
- Upload Word documents containing news articles
- Automatic matching of people to relevant news paragraphs
- AI-powered risk analysis with personalized recommendations
- Real-time progress tracking via Server-Sent Events
- Risk categorization (LOW, MEDIUM, HIGH, CRITICAL)
- Urgency classification (MONITORING, ATTENTION, IMMEDIATE, EMERGENCY)
- Statistics and visualizations of risk distribution
- Filtering results by risk category
- Export results to JSON

## Requirements

- PHP 8.1+
- Composer
- MySQL database (via XAMPP or similar)
- Laravel 10+

## Installation

1. Clone this repository:
   ```
   git clone [repository-url]
   cd potential-risk-intelligence
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Copy the environment file:
   ```
   cp .env.example .env
   ```

4. Configure your database in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=analisis_risiko
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Create the database in MySQL:
   ```sql
   CREATE DATABASE analisis_risiko;
   ```

6. Run database migrations:
   ```
   php artisan migrate
   ```

7. Generate application key:
   ```
   php artisan key:generate
   ```

8. Create storage link:
   ```
   php artisan storage:link
   ```

9. Start the development server:
   ```
   php artisan serve
   ```

## Usage

1. Navigate to the homepage at `http://localhost:8000`
2. Upload an Excel file containing person data (columns should include "nama" and "jabatan")
3. Upload a Word document containing news articles
4. Submit the form to start the analysis process
5. Monitor the progress in real-time
6. View the results in the table below
7. Filter results by risk category if needed
8. Export results to JSON using the export button

## Technology Stack

- Laravel 10 (PHP framework)
- MySQL (database)
- PhpSpreadsheet (for Excel parsing)
- PhpWord (for Word document parsing)
- OpenRouter API with Meta Llama 4 Maverick model (for AI analysis)
- Bootstrap 5 (UI framework)
- Server-Sent Events (for real-time progress updates)

## Project Structure

- `app/Models/AnalysisResult.php` - Model for analysis results
- `app/Http/Controllers/AnalysisController.php` - Main controller
- `app/Services/DocumentParserService.php` - Service for parsing Excel and Word documents
- `app/Services/AnalysisApiService.php` - Service for AI API integration
- `app/Services/ProgressService.php` - Service for progress tracking
- `resources/views/` - Blade templates for the UI

## License

This project is licensed under the MIT License.
