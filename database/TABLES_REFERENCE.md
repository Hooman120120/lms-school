# LMS School - Database Tables Quick Reference

## All 33 Tables at a Glance

### 🔐 Authentication & Users (4 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `dbpersonal` | Students and personal information | idpersonal, codemeli, fname, lname, paye |
| `dblogin` | User login credentials | idlogin, username, password, role |
| `dbadmin` | Admin users, teachers, staff | idadmin, username, fname, lname, adminrole |
| `school_admin_roles` | Role definitions for staff | id, role_key, title, is_active |

### 📍 Geographic (2 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `state` | Iranian provinces (31 total) | id, name |
| `city` | Cities within provinces | id, state_id, name |

### 🎓 Academic Structure (9 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `institution_levels` | Grade levels, groups, fields | id, kind, value, slug, label |
| `codtbl_course` | Courses/Subjects | codecourse, namecourse, coursefee, paye |
| `dbdars` | Subject/Lesson details | id, codecourse, namedars, link_class |
| `dblesson` | Individual lessons | id, codecourse, namelesson |
| `classes` | Class sections | id, codecourse, class_name, teacher_username |
| `class_dars` | Maps classes to subjects | id, class_id, dars_id |
| `course_teacher` | Assigns teachers to classes | id, class_id, teacher_username |
| `course_teacher_dars` | Assigns teachers to subjects | id, class_id, dars_id, teacher_username |
| `dbcourse` | Student course enrollment | idcourse, username, codecourse, status |

### 👤 Student Types (2 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `type_vip` | Student type categories | id, name |
| `dbpersonal_type_vip` | Maps students to types | id, dbpersonal_id, type_vip_id |

### ✅ Attendance & Evaluation (4 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `register_class_events` | Attendance/grade events | id, class_id, event_date, event_name, type |
| `register_class_attendance` | Attendance records | id, register_class_events_id, username, status |
| `register_class_evaluation` | Grades and scores | id, register_class_events_id, username, score |
| `register_class_discipline` | Discipline records | id, class_id, username, score, reason |

### 👨‍🏫 Teacher Management (4 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `teacher_lesson_plans` | Lesson planning by teacher | id, teacher_username, class_id, dars_id, plan_date |
| `teacher_groups` | Teacher groups | id, title, description |
| `teacher_group_members` | Teacher group membership | id, group_id, admin_id |
| `calendar_event_teacher_groups` | Maps groups to calendar events | id, event_id, group_id |

### 📢 Notifications & Communication (4 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `school_notifications` | System notifications | id, event_type, title, body, created_at |
| `school_notification_reads` | Notification read tracking | notification_id, admin_username, read_at |
| `calendar_events` | School calendar events | id, title, event_date, is_academic |
| `calendar_event_teacher_groups` | Teacher group calendar access | id, event_id, group_id |

### 💼 Staff Management (4 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `school_staff_courses` | Staff course access permissions | id, admin_username, codecourse |
| `school_staff_insurance` | Insurance records | id, admin_username, provider, status |
| `school_staff_monthly_payments` | Salary/payments | id, admin_username, payment_month, amount |
| `school_assigned_tasks` | Task management | id, title, assigned_to, due_date, status |

### 💰 Financial System (5 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `dbfactor` | Invoices/Billing | idfactor, username, codecourse, fee, status |
| `db_wallet` | Student wallets/balances | id, username, fee, status |
| `dbdiscount` | Discount codes | id, discount, percent, max_usage |
| `checkout` | Student course purchases | id, username, coursecode |
| `checkout_collague` | Group/colleague purchases | id, useradder, username, coursecode |

### 📚 Content (2 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `course_demo` | Demo/preview content | id, codecourse, video_url |
| `documention` | Documentation/files | id, title, content, file_path |

### ⚙️ System Configuration (3 tables)

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `setting_public` | Global settings | id, setting_key, setting_value |
| `bulk_uploads` | Batch import tracking | id, filename, uploaded_by, uploaded_at |
| `temporary_personal` | Temporary data storage | id, temp_data, created_at |

---

## Table Relationships

```
dbpersonal
├── dbpersonal_type_vip → type_vip
├── dbcourse → codtbl_course
├── register_class_attendance
├── register_class_evaluation
└── register_class_discipline

dbadmin
├── school_admin_roles
├── course_teacher
├── register_class_events
├── teacher_lesson_plans
└── teacher_groups

codtbl_course
├── dbdars
├── dblesson
├── classes
└── dbfactor

classes
├── class_dars → dbdars
├── course_teacher → dbadmin
├── course_teacher_dars
├── register_class_events
└── register_class_discipline

dbdars
├── class_dars
├── course_teacher_dars
└── teacher_lesson_plans

dblesson
└── teacher_lesson_plans

state
└── city

teacher_groups
├── teacher_group_members → dbadmin
└── calendar_event_teacher_groups

calendar_events
└── calendar_event_teacher_groups → teacher_groups
```

---

## Common Queries

### Get All Students
```sql
SELECT * FROM dbpersonal;
```

### Get Student by Code
```sql
SELECT * FROM dbpersonal WHERE codemeli = '1234567890';
```

### Get Courses for a Grade
```sql
SELECT DISTINCT c.* FROM codtbl_course c
WHERE c.paye = 10; -- Grade 10
```

### Get Students Enrolled in a Course
```sql
SELECT p.* FROM dbpersonal p
INNER JOIN dbcourse dc ON p.codemeli = dc.username
WHERE dc.codecourse = 1;
```

### Get Teacher's Classes
```sql
SELECT c.* FROM classes c
WHERE c.teacher_username = 'admin';
```

### Get Attendance for a Class
```sql
SELECT ra.username, ra.status, COUNT(*) as days
FROM register_class_attendance ra
INNER JOIN register_class_events rce ON ra.register_class_events_id = rce.id
WHERE rce.class_id = 1
GROUP BY ra.username;
```

### Get Student Grades
```sql
SELECT re.username, re.score, re.created_at
FROM register_class_evaluation re
WHERE re.username = '1234567890'
ORDER BY re.created_at DESC;
```

### Get All Admins by Role
```sql
SELECT da.username, da.fname, da.lname, sar.title
FROM dbadmin da
LEFT JOIN school_admin_roles sar ON da.school_role_id = sar.id
WHERE sar.id = 1; -- Manager role
```

### Get Student Balance
```sql
SELECT username, SUM(fee) as balance FROM db_wallet
WHERE status = 'موفق'
GROUP BY username;
```

---

## Table Statistics

| Category | Count | Records |
|----------|-------|---------|
| User Management | 4 | ~100-1000 |
| Academic | 9 | ~50-500 |
| Attendance | 4 | ~10000+ |
| Staff | 4 | ~20-200 |
| Financial | 5 | ~100-5000 |
| Content | 2 | ~50-500 |
| System | 3 | ~100-1000 |
| **TOTAL** | **33** | **~11k+ rows** |

---

## Field Data Types

### Integer (INT)
- Auto-increment IDs
- Codes and numbers
- Status codes (0, 1, 2, 3)

### VARCHAR
- Usernames (max 12)
- Names (max 50-100)
- Titles (max 100-190)
- Codes (max 20)

### Date & DateTime
- Birth dates
- Event dates
- Creation timestamps
- Updated timestamps

### DECIMAL
- Scores (0-20)
- Percentages (0-100)
- Money amounts (BIGINT for cents)

### Text & LongText
- Descriptions
- Notes
- Comments
- Temporary data

### Enum
- Statuses (active, inactive, pending, done)
- Types (school, institute)
- Roles (0, 1, 2, 3)

---

## Indexes

All tables have:
- ✅ Primary Key (auto-increment)
- ✅ Foreign Keys (where appropriate)
- ✅ Unique Constraints (for usernames, codes)
- ✅ Performance Indexes (on frequently queried columns)

---

## Default Data Included

### Provinces
All 31 Iranian provinces with major cities

### Grades (School Mode)
- Grades 1-12 across 3 categories
- Selectable grades 5-12 in enrollment

### Student Types
- عادی (Normal)
- هنرمند (Talented)
- ورزشکار (Athlete)
- نیازمند بیشتر (Needs Support)
- استثنایی (Exceptional)

### Admin Roles
- 9 pre-configured roles
- Manager, Deputies, Counselor, Teacher

### Settings
- institution_type: 'school' (default)
- site_title: 'موسسه هنرفناوری'

---

## Character Set & Collation

| Table | Charset | Collation |
|-------|---------|-----------|
| Most tables | utf8mb4 | utf8mb4_general_ci |
| Legacy tables | utf8 | utf8_general_ci |

All support **Farsi (Persian)** characters fully.

---

## Constraints & Rules

### Foreign Key Constraints
- CASCADE ON DELETE: Related records are deleted
- CASCADE ON UPDATE: Changes propagate to related records

### Unique Constraints
- Username (unique across system)
- National Code (unique per person)
- Course enrollment (unique per student-course)
- Various other business logic constraints

### Check Constraints
- Status enums (only valid values allowed)
- Type enums (school/institute, active/inactive)

---

## Storage Estimates

| Size | Details |
|------|---------|
| Empty | ~5 MB (structure only) |
| 100 Students | ~10 MB |
| 1000 Students | ~50 MB |
| 10000 Students | ~500 MB |

---

## Maintenance

### Regular Tasks
1. **Optimize** tables monthly
2. **Analyze** table statistics
3. **Check** table integrity
4. **Backup** daily/weekly
5. **Monitor** disk space

### Optimization
```sql
OPTIMIZE TABLE dbpersonal;
OPTIMIZE TABLE register_class_attendance;
OPTIMIZE TABLE register_class_evaluation;
```

### Analysis
```sql
ANALYZE TABLE dbpersonal;
ANALYZE TABLE codtbl_course;
```

---

## Version Information

- **Created**: 2026-09-19
- **MySQL Version**: 5.7+ / MariaDB 10.2+
- **PHP Version**: 7.2+
- **Character Set**: UTF-8 MB4
- **Tables**: 33
- **Primary Keys**: 33
- **Foreign Keys**: 35+
- **Indexes**: 50+

---

**Last Updated**: 2026-09-19  
**For detailed information, see README.md and SETUP_GUIDE.md**
