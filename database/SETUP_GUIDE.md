# LMS School - Complete Database Setup Guide

## 📋 Quick Summary

This package contains everything needed to set up a complete LMS (Learning Management System) database from scratch. The system supports both **School** and **Institute** modes and includes 33 pre-configured tables with all necessary relationships and default data.

## 🎯 What's Included

### Files in `/database` folder:

1. **complete_schema.sql** - The complete database schema with all 33 tables
2. **import.php** - Web-based import tool (easiest method)
3. **README.md** - Detailed documentation
4. **SETUP_GUIDE.md** - This file
5. **migrations/** - Additional migration files for advanced features

## 🚀 Quick Start (3 Steps)

### Step 1: Update Database Credentials

Edit `dbconfig.php` in the project root:

```php
define('DB_HOST', 'localhost');    // Your MySQL host
define('DB_USER', 'root');         // Your MySQL user
define('DB_PASS', '');             // Your MySQL password
define('DB_NAME', 'lms_school');   // Your database name
```

### Step 2: Choose Import Method

#### **Method A: Web Browser (Easiest)**
1. Start your Apache/PHP server
2. Navigate to: `http://localhost/lms-school/database/import.php`
3. Fill in database credentials
4. Click "Import Database"
5. Wait for success message

#### **Method B: MySQL Command Line**
```bash
# First, create the database
mysql -u root -p -e "CREATE DATABASE lms_school CHARACTER SET utf8mb4"

# Then import the schema
mysql -u root -p lms_school < database/complete_schema.sql
```

#### **Method C: phpMyAdmin**
1. Log in to phpMyAdmin
2. Click "New" to create database
3. Name it: `lms_school`
4. Go to Import tab
5. Upload `complete_schema.sql`
6. Click Go

### Step 3: Verify Installation

Access the admin panel:
- URL: `http://localhost/lms-school/admin/login.php`
- Username: `admin`
- Password: `admin123`

## 📊 Database Tables (33 Total)

### Category 1: User & Authentication (4 tables)
```
dbpersonal              ← Students and people
dblogin                 ← User login credentials
dbadmin                 ← Admin users & teachers
school_admin_roles      ← Role definitions
```

### Category 2: Geographic (2 tables)
```
state                   ← 31 Iranian provinces
city                    ← Cities within provinces
```

### Category 3: Academic Structure (9 tables)
```
institution_levels      ← Grade levels & categories
codtbl_course           ← Courses/Subjects
dbdars                  ← Subject details
dblesson                ← Individual lessons
classes                 ← Class sections
class_dars              ← Class-subject mapping
course_teacher          ← Teacher-class assignment
course_teacher_dars     ← Teacher-subject-class assignment
dbcourse                ← Student course enrollment
```

### Category 4: Student Management (2 tables)
```
type_vip                ← Student types (Gifted, Athlete, etc.)
dbpersonal_type_vip     ← Student type assignments
```

### Category 5: Attendance & Evaluation (4 tables)
```
register_class_events   ← Class events (attendance, grades)
register_class_attendance ← Attendance records
register_class_evaluation ← Grade/score records
register_class_discipline ← Discipline records
```

### Category 6: Teacher Management (4 tables)
```
teacher_lesson_plans    ← Lesson planning
teacher_groups          ← Teacher groups
teacher_group_members   ← Group membership
calendar_event_teacher_groups ← Calendar access
```

### Category 7: Communication (4 tables)
```
school_notifications    ← System notifications
school_notification_reads ← Read status
calendar_events         ← School calendar
calendar_event_teacher_groups ← Group calendar access
```

### Category 8: Staff Management (3 tables)
```
school_staff_courses    ← Staff course access
school_staff_insurance  ← Insurance records
school_staff_monthly_payments ← Salary records
school_assigned_tasks   ← Task management
```

### Category 9: Financial (4 tables)
```
dbfactor                ← Invoices
db_wallet               ← Student balances
dbdiscount              ← Discount codes
checkout                ← Course purchases
checkout_collague       ← Group purchases
```

### Category 10: Content & Docs (2 tables)
```
course_demo             ← Demo content
documention             ← Documentation files
```

### Category 11: System (3 tables)
```
setting_public          ← Global settings
bulk_uploads            ← Batch import records
temporary_personal      ← Temporary data
```

## 🔐 Default Credentials

After import, default login is available:

```
Username: admin
Password: admin123
```

⚠️ **IMPORTANT**: Change this password immediately!

To change admin password in MySQL:

```sql
UPDATE dblogin 
SET password = MD5('your_new_password') 
WHERE username = 'admin';

UPDATE dbadmin 
SET password = MD5('your_new_password') 
WHERE username = 'admin';
```

## 🎓 Institution Types

The system supports two modes:

### School Mode (Default)
- Grades 1-12 (elementary to high school)
- Academic year structure
- Parent/guardian module
- Report cards and discipline
- Morning/afternoon shifts
- Student transcript system

### Institute Mode
- Custom course levels
- Term/semester based
- Adult learners focus
- Course-focused (no grades)
- No parent module
- Certificate system

To switch modes:

```sql
-- Switch to institute mode
UPDATE setting_public 
SET institution_type = 'institute', institution_type = 'institute' 
WHERE setting_key = 'institution_type';

-- Switch back to school mode
UPDATE setting_public 
SET institution_type = 'school', institution_type = 'school' 
WHERE setting_key = 'institution_type';
```

## 📚 Pre-loaded Data

### Grade Levels (School Mode)
- **ابتدایی** (Elementary): Classes 1-6
- **متوسطه اول** (Middle): Classes 7-9
- **متوسطه دوم** (High School): Classes 10-12

### Student Types
- عادی (Normal)
- هنرمند (Gifted/Talented)
- ورزشکار (Athlete)
- نیازمند بیشتر (Needs Support)
- استثنایی (Exceptional)

### Provinces (31 Iranian States)
All Iranian provinces with major cities included

### Admin Roles
- مدیر (Manager)
- معاون کل (General Deputy)
- معاون پایه (Grade Deputy)
- معاون اجرایی (Executive Deputy)
- معاون فرهنگی انضباطی (Cultural & Disciplinary Deputy)
- مشاور (Counselor)
- معاون فنی (Technical Deputy)
- معاون مالی (Financial Deputy)
- معلم (Teacher)

## 🔧 Technical Specifications

| Aspect | Details |
|--------|---------|
| **Database Engine** | MySQL 5.7+ / MariaDB 10.2+ |
| **Character Set** | UTF-8 MB4 (supports Farsi + emojis) |
| **Collation** | utf8mb4_general_ci |
| **Tables** | 33 total |
| **Estimated Size** | 10-15 MB (empty database) |
| **Primary Keys** | All tables have auto-increment PKs |
| **Foreign Keys** | Properly configured with CASCADE |
| **Indexes** | Optimized for common queries |

## 🗂️ Directory Structure

```
lms-school/
├── database/
│   ├── complete_schema.sql      ← Main schema file
│   ├── import.php               ← Web import tool
│   ├── README.md                ← Full documentation
│   ├── SETUP_GUIDE.md           ← This file
│   └── migrations/              ← Advanced features
│       ├── 20260916_*.sql
│       ├── 20260917_*.sql
│       └── 20260919_*.sql
├── dbconfig.php                 ← Database connection
├── admin/
├── student/
├── teacher/
└── ... (other directories)
```

## 🚨 Important Configuration

### dbconfig.php Settings

```php
// Database credentials
define('DB_HOST', '192.168.1.225');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'myhona_myhonar');

// Character set (IMPORTANT for Farsi)
$conn->query("SET NAMES utf8mb4");
```

### Character Set for Terminals

```sql
-- Check character set
SHOW VARIABLES LIKE 'character_set%';

-- Set to UTF-8 MB4 for Farsi support
SET CHARACTER_SET_CLIENT = 'utf8mb4';
SET CHARACTER_SET_RESULTS = 'utf8mb4';
SET CHARACTER_SET_CONNECTION = 'utf8mb4';
```

## 📋 Database Backup & Restore

### Creating Backup

```bash
# Full backup with data
mysqldump -u root -p lms_school > backup_full_$(date +%Y%m%d_%H%M%S).sql

# Backup structure only
mysqldump -u root -p --no-data lms_school > backup_structure_$(date +%Y%m%d_%H%M%S).sql

# Backup with compression
mysqldump -u root -p lms_school | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Restoring Backup

```bash
# Restore from file
mysql -u root -p lms_school < backup_full_20260919_120000.sql

# Restore from compressed file
gunzip < backup_20260919.sql.gz | mysql -u root -p lms_school
```

## 🐛 Troubleshooting

### Issue: "Table already exists" error
**Solution:** Drop the database and reimport
```sql
DROP DATABASE lms_school;
```

### Issue: "Unknown charset utf8mb4"
**Solution:** Upgrade MySQL or use utf8 instead
```sql
ALTER TABLE table_name CONVERT TO CHARACTER SET utf8;
```

### Issue: "Foreign key constraint fails"
**Solution:** Temporarily disable FK checks
```sql
SET FOREIGN_KEY_CHECKS=0;
-- Import here
SET FOREIGN_KEY_CHECKS=1;
```

### Issue: Slow import
**Solution:** Increase MySQL buffer size
```sql
SET GLOBAL max_allowed_packet = 256M;
SET GLOBAL sort_buffer_size = 256M;
```

### Issue: Can't connect from PHP
**Check:**
1. MySQL server is running
2. Credentials are correct in dbconfig.php
3. Database user has proper permissions
4. PDO MySQL extension is enabled

## ✅ Post-Installation Checklist

- [ ] Database created and schema imported
- [ ] dbconfig.php updated with correct credentials
- [ ] Admin credentials changed from default
- [ ] Test login at `/admin/login.php`
- [ ] Verify all tables exist: `SHOW TABLES;`
- [ ] Create database backup
- [ ] Set up daily backup schedule
- [ ] Configure institution type (school/institute)
- [ ] Add admin users
- [ ] Add teachers
- [ ] Add courses and subjects
- [ ] Import student data
- [ ] Test all major features

## 📞 Support Resources

1. **Database Logs**: Check MySQL error log
2. **Application Logs**: Check PHP error logs
3. **phpMyAdmin**: Visual database explorer
4. **MySQL Workbench**: Advanced database tool
5. **Documentation**: See README.md for details

## 🔒 Security Notes

1. **Never use default password in production**
2. **Restrict database access to localhost only**
3. **Use strong passwords for DB users**
4. **Enable SSL for remote connections**
5. **Regular backups are essential**
6. **Monitor database logs for suspicious activity**

## 📈 Performance Tips

1. **Regular Maintenance**
   ```sql
   OPTIMIZE TABLE table_name;
   ANALYZE TABLE table_name;
   REPAIR TABLE table_name;
   ```

2. **Index Optimization**
   ```sql
   -- Check for unused indexes
   EXPLAIN SELECT ... ;
   ```

3. **Query Optimization**
   - Use proper WHERE clauses
   - Avoid SELECT *
   - Use JOINs efficiently

4. **Server Settings**
   ```sql
   SET GLOBAL innodb_buffer_pool_size = 1G;
   SET GLOBAL query_cache_size = 256M;
   ```

## 📝 Version History

- **v1.0** (2026-09-19)
  - Initial release
  - 33 tables
  - Full school/institute support
  - Farsi language support

## 📄 License

This database schema and documentation are provided as part of the LMS School system.

---

## 🎯 Next Steps

1. **Import the database** using one of the three methods
2. **Verify** the installation by logging in with default credentials
3. **Change** the admin password immediately
4. **Configure** system settings in the admin panel
5. **Add** teachers, courses, and students
6. **Train** users and start using the system

---

**For detailed information, see README.md**

**Created**: 2026-09-19  
**System Version**: 1.0  
**Database Compatibility**: MySQL 5.7+, MariaDB 10.2+
