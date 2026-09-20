# LMS School - Database Setup Guide

## Overview

The LMS School system is a complete educational management system that supports both schools and institutes. This guide explains how to set up the database from scratch using the provided SQL schema.

## Database Configuration

Before importing the schema, ensure your database connection details in `dbconfig.php` are correct:

```php
define('DB_HOST', 'localhost');      // Your MySQL host
define('DB_USER', 'root');           // Your MySQL user
define('DB_PASS', '');               // Your MySQL password
define('DB_NAME', 'lms_school');     // Your database name
```

## How to Import the Complete Schema

### Option 1: Using MySQL CLI

```bash
mysql -h localhost -u root -p database_name < database/complete_schema.sql
```

### Option 2: Using phpMyAdmin

1. Log in to phpMyAdmin
2. Create a new database (e.g., `lms_school`)
3. Select the database
4. Go to "Import" tab
5. Choose the `database/complete_schema.sql` file
6. Click "Go" to execute

### Option 3: Using PHP Script

Run the following PHP script once:

```php
<?php
$conn = new PDO('mysql:host=localhost', 'root', '');
$sql = file_get_contents('database/complete_schema.sql');

// Split SQL statements and execute each
$statements = array_filter(array_map('trim', explode(';', $sql)), function($s) {
    return !empty($s) && strpos($s, '--') !== 0;
});

foreach ($statements as $statement) {
    try {
        $conn->exec($statement . ';');
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Database imported successfully!";
?>
```

## Database Tables Overview

### Core Tables (33 main tables)

#### 1. **User & Authentication**
- `dbpersonal` - Students and personal information
- `dblogin` - Login credentials for all users
- `dbadmin` - Admin users and teachers
- `school_admin_roles` - Role definitions for school staff

#### 2. **Geographic Data**
- `state` - Iranian states/provinces
- `city` - Cities within states

#### 3. **Academic Structure**
- `institution_levels` - Grade levels and course categories (school vs institute modes)
- `codtbl_course` - Courses (e.g., Math, English)
- `dbdars` - Subjects/Lessons within courses
- `dblesson` - Individual lessons
- `classes` - Class sections
- `class_dars` - Mapping classes to subjects
- `course_teacher` - Teacher-class assignments
- `course_teacher_dars` - Teacher-subject-class assignments
- `dbcourse` - Student course enrollments

#### 4. **Student Management**
- `type_vip` - Student types (Normal, Gifted, Athlete, etc.)
- `dbpersonal_type_vip` - Student type assignments

#### 5. **Attendance & Evaluation**
- `register_class_events` - Class events (attendance, grades, etc.)
- `register_class_attendance` - Attendance records
- `register_class_evaluation` - Student grades and scores
- `register_class_discipline` - Discipline records

#### 6. **Teacher Management**
- `teacher_lesson_plans` - Teacher lesson planning
- `teacher_groups` - Group membership for teachers
- `teacher_group_members` - Individual teacher group memberships
- `calendar_event_teacher_groups` - Teacher access to calendar events

#### 7. **Communication & Notifications**
- `school_notifications` - System notifications
- `school_notification_reads` - Notification read status
- `calendar_events` - School calendar events
- `calendar_event_teacher_groups` - Teacher group calendar access

#### 8. **Staff Management**
- `school_staff_courses` - Staff course access
- `school_staff_insurance` - Staff insurance records
- `school_staff_monthly_payments` - Salary records
- `school_assigned_tasks` - Task management

#### 9. **Financial System**
- `dbfactor` - Invoices/Billing
- `db_wallet` - Student wallet/balance
- `dbdiscount` - Discount codes
- `checkout` - Student course purchases
- `checkout_collague` - Colleague-assisted purchases

#### 10. **Content & Documentation**
- `course_demo` - Demo content for courses
- `documention` - Documentation/files

#### 11. **System Management**
- `setting_public` - Global system settings
- `bulk_uploads` - Bulk student import records
- `temporary_personal` - Temporary data storage

## Key Features

### Institution Type Support
The system supports two modes:

1. **School Mode** (default)
   - Grades 1-12 (پایه اول تا دوازدهم)
   - Academic year based
   - Guardian/parent module
   - Report cards and discipline tracking
   - Shift management (morning/afternoon)

2. **Institute Mode**
   - Custom course levels (مقدماتی، متوسط، پیشرفته)
   - Term-based scheduling
   - No parent/guardian module
   - Custom field definitions

Switch modes by modifying `setting_public.institution_type`:
```sql
UPDATE setting_public SET institution_type = 'institute' WHERE setting_key = 'institution_type';
```

### Default Login Credentials

After importing the schema:

```
Username: admin
Password: admin123
```

⚠️ **IMPORTANT**: Change this password immediately in production!

## Grade Levels (Pre-loaded)

### School Mode Grades
- **ابتدایی (Elementary)**: Grades 1-6
- **متوسطه اول (Middle)**: Grades 7-9
- **متوسطه دوم (High School)**: Grades 10-12

### Institute Mode Courses
- **مقدماتی (Beginner)**
- **متوسط (Intermediate)**
- **پیشرفته (Advanced)**

## Student Types (Pre-loaded)

- عادی (Normal)
- هنرمند (Gifted)
- ورزشکار (Athlete)
- نیازمند بیشتر (Needs Support)
- استثنایی (Exceptional)

## School Admin Roles

- مدیر (Manager)
- معاون کل (General Deputy)
- معاون پایه (Grade Deputy)
- معاون اجرایی (Executive Deputy)
- معاون فرهنگی انضباطی (Cultural & Disciplinary Deputy)
- مشاور (Counselor)
- معاون فنی (Technical Deputy)
- معاون مالی (Financial Deputy)
- معلم (Teacher)

## Migration Files

Additional migration files are included in `database/migrations/` for:

- Teacher permissions and capabilities
- Class register (دفتر کلاسی) enhancements
- School-specific notifications
- Institutional financial and technical roles
- Teacher group management
- Lesson planning features

These migrations are applied automatically by the application when needed.

## Character Encoding

The system uses:
- **Default**: utf8mb4 for new tables (supports emojis and extended characters)
- **Compatibility**: utf8 for legacy tables (for backward compatibility)

All Persian/Farsi text is supported.

## Database Backup

To backup the database:

```bash
mysqldump -h localhost -u root -p lms_school > backup_$(date +%Y%m%d).sql
```

To restore from backup:

```bash
mysql -h localhost -u root -p lms_school < backup_20231225.sql
```

## Troubleshooting

### Common Import Errors

**Error: "Table already exists"**
- The table already exists in the database. Use `DROP DATABASE` to delete and start fresh, or modify the import script to use `DROP TABLE IF EXISTS`.

**Error: "Unknown charset"**
- Ensure your MySQL server supports utf8mb4. Check with:
  ```sql
  SHOW CHARSET LIKE 'utf8mb4';
  ```

**Error: "Foreign key constraint fails"**
- This shouldn't happen with the provided schema, but if it does:
  1. Disable foreign key checks: `SET FOREIGN_KEY_CHECKS=0;`
  2. Import the schema
  3. Re-enable: `SET FOREIGN_KEY_CHECKS=1;`

## Next Steps After Import

1. **Update admin password**:
   ```php
   // In admin panel, change password from admin123 to secure password
   ```

2. **Configure system settings** in Admin Dashboard:
   - School/Institute name
   - Academic year
   - Terms/Semesters
   - School hours

3. **Add states and cities** (optional - defaults included)

4. **Import courses and subjects**

5. **Add admin users and teachers**

6. **Set up student enrollment**

## System Architecture

```
┌─────────────────────────────────────────┐
│         Web Interface (PHP)              │
├─────────────────────────────────────────┤
│      Application Logic (Controllers)     │
├─────────────────────────────────────────┤
│    Database Abstraction (PDO MySQL)     │
├─────────────────────────────────────────┤
│      MySQL Database (33 Tables)          │
└─────────────────────────────────────────┘
```

## Support

For issues or questions:
1. Check the application logs
2. Verify database permissions
3. Ensure character encoding compatibility
4. Review MySQL error logs

## License

This database schema is part of the LMS School system.

---

**Last Updated**: 2026-09-19
**Version**: 1.0
**Schema Size**: ~10-15MB (depending on data volume)
