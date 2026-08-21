# Maktab Kauzariyya - Developer Spec, Style Guide & Build Roadmap

This document serves as the master guide, design specification, and progression tracker for the Maktab Kauzariyya portal. It bridges the brand's visual identity, offline guidelines (`Maktab_Kauzariyya_Guidelines.pdf`), directory structure, and future execution phases.

---

## 1. Visual Brand Identity & Styles
All future pages and dashboards must adhere to the official brand guidelines defined in **`Visual Identity Maktab Kauzariyya.pdf`** and configured inside **`assets/css/style.css`**:

### 🎨 Color Palette (Hex Codes)
| Color Role | Hex Code | Visual Application |
| :--- | :--- | :--- |
| **Cream** | `#ecf3d6` | Primary foreground text & high-contrast labels. |
| **Cream Light** | `#f1f6d0` | Hover states for text and light highlights. |
| **Green Fresh** | `#6dcc8d` | Primary brand accent, success messages, & main buttons. |
| **Teal Mid** | `#41aebd` | Secondary accent, link hovers, and intermediate cards. |
| **Teal Dark** | `#3e6d6c` | Borders, subtle highlights, & placeholder text. |
| **Navy** | `#123b47` | Midground panels & glassmorphic backdrops. |
| **Navy Deep** | `#0e2e38` | Deep background fill & drop-shadow colors. |

### ✍️ Typography
* **Main UI Font**: `'Quicksand', sans-serif` (applied globally to headings, tables, and buttons).
* **Italic / Accent Font**: `'Urbanist', sans-serif` (used for quotes, descriptive labels, and microcopy).

### 💎 UI Design Rules (Glassmorphism)
To maintain the sleek, modern design language:
* **Background Blobs**: Use absolute-positioned blurred organic circles behind layout wrappers (`animate-blob-1`, `animate-blob-2`).
* **Glass Panels**: Add `.glass-panel` for primary content backdrops (uses `background: rgba(109,204,141,0.04)` and `backdrop-filter: blur(24px)`).
* **Glass Cards**: Add `.glass-card` for interactive grids (uses 8% border opacity and translates `translateY(-2px)` on hover).
* **Inputs**: Use `.glass-input` with styled placeholders. Dropdowns must use dark options (`background-color: #0e2e38`).

### 🏷️ Logos & Assets
* **Logo Mark**: Dark (`assets/images/logo-dark.png`) & Light (`assets/images/logo-light.png`).
* **Backgrounds**: Grid pattern elements like `brand-pattern-dark.png` or `brand-pattern-light.png`.

---

## 2. Directory Structure

We maintain a clean **separation of concerns** with zero external dependencies to keep deployment on shared hosting (like Bluehost) fast and simple:

```
makthab-kauzariyya/
├── .env                              # Standard DB credentials (git-ignored)
├── .env.example                      # Template for environment settings
├── .htaccess                         # URL routing & block rules for .env
├── .gitignore                        # Git ignore settings
├── index.php                         # Homepage (Stats counter & responsive menu)
├── api.php                           # Backend API (JSON endpoint for dashboard)
├── Maktab_Kauzariyya_Guidelines.pdf  # PDF copy of teacher guidelines
│
├── assets/                           # Global assets folder
│   ├── css/
│   │   └── style.css                 # Global stylesheets (glassmorphism UI)
│   └── images/                       # Branding images, logo mark, background
│
├── includes/                         # Global PHP helpers & setups
│   └── db.php                        # Zero-dependency DB connection (Musabaqa style)
│
├── auth/                             # User Authentication
│   ├── login.php                     # Log in handler (Session management)
│   └── logout.php                    # Log out handler (Clear session)
│
├── database/                         # Database Migration & Schema Seeding
│   ├── install_users.sql             # Users & roles structure
│   └── install_coordinator.sql       # Courses, classes, & academic relationships
│
└── coordinator/                      # Modular Academic Board
    ├── includes/
    │   ├── header.php                # Sidebar loader & login guard check
    │   ├── sidebar.php               # Category-grouped navigation
    │   └── footer.php                # Global delete dialog & modals JS
    ├── index.php                     # Overview panel (Global counters grid)
    ├── courses.php                   # Course & class registries (Add/edit/delete)
    ├── teachers.php                  # Teacher assignments (Pivot mapping)
    ├── supervisors.php               # Supervisor registry (Supervised classes link)
    └── students.php                  # Student registry (Enrollment, parent details)
```

---

## 3. Guidelines PDF mapping (`Maktab_Kauzariyya_Guidelines.pdf`)

The teacher guidelines and administrative duties are officially compiled in **`Maktab_Kauzariyya_Guidelines.pdf`** (located at the root and desktop). Below is the mapping of how these offline rules are implemented in the online portal:

| Section in PDF | Offline Guideline / Rule | Digital Portal Implementation |
| :--- | :--- | :--- |
| **1. General Guidelines** | Commencing classes with specific Duas | Dynamic header banner on the Teacher Class Room showing the matching Dua based on class type (General vs Hifz). |
| **1. General Guidelines** | Updating Google Sheets daily (leaves, etc.) | Daily Attendance Checkbox panel in the Teacher Portal, writing to the database instead of spreadsheet rows. |
| **1. General Guidelines** | Saturday Activities & WhatsApp Prayer Charts | Student Activity Upload portal where students upload their checklist photos. |
| **3. Curriculum - Tajweed** | Noorani Qaida (28 Lessons target) | A progress slider (1 to 28) on the Student Log. The maximum limit is fetched dynamically from the database. |
| **3. Curriculum - Nazira** | 5 Juz target and check articulation | An input log for current Juz (1-5) and page, featuring checklists for articulation parameters (*Sihhath*, *Waqf*, *Flow*). |
| **3. Curriculum - Hifz** | Sabre / Sabqi / Manzil (Three daily portions) | Progression log containing input boxes for *Sabak* (New lines), *Sabqi* (Recitation verification), and *Manzil* (Audio status). |
| **3. Curriculum - Hifz** | Hifz Memorization Path (Core Surahs first, then reverse 30–26, then 1...) | Dynamic milestone roadmap on the student view, showing completed Juz nodes chronologically. |
| **2. Responsibilities** | Weekly class audits by the supervisor | An evaluation dashboard for supervisors to rate active Google Meet sessions. |

---

## 4. Configurable Syllabus Schema (Future-Proofing)

To support changes in syllabus volumes (e.g., altering the 28 Lessons of Tajweed or 5 Juz of Nazira), we store targets dynamically inside the database.

### Schema Adjustments:
```sql
-- Alter courses table to specify milestone structures
ALTER TABLE `courses` 
ADD COLUMN `target_type` ENUM('lesson', 'juz') NOT NULL DEFAULT 'lesson',
ADD COLUMN `total_targets` INT NOT NULL DEFAULT 1;

-- Seed targets dynamically
UPDATE `courses` SET `target_type` = 'lesson', `total_targets` = 28 WHERE `code` = 'TJ';  -- Tajweed (28 Lessons)
UPDATE `courses` SET `target_type` = 'juz',    `total_targets` = 5  WHERE `code` = 'NZ';  -- Nazira (5 Juz)
UPDATE `courses` SET `target_type` = 'juz',    `total_targets` = 30 WHERE `code` = 'HZ';  -- Hifz (30 Juz)
```

---

## 5. Hifz Rules Validation Engine (Business Logic)

In the teacher's dashboard, when log entries are loaded for the daily class registry, the system must perform validation to lock/unlock progression inputs:

### Lock Rules Logic (Pseudocode):
```php
function get_student_progression_status($pdo, $studentId, $classDate) {
    // 1. Check yesterday's Sabqi (Juz Lesson) status
    $yesterday = date('Y-m-d', strtotime($classDate . ' -1 day'));
    $sabqiStmt = $pdo->prepare("SELECT sabqi_completed FROM progress_logs WHERE student_id = ? AND logged_date = ?");
    $sabqiStmt->execute([$studentId, $yesterday]);
    $sabqiCompleted = $sabqiStmt->fetchColumn();
    
    if ($sabqiCompleted === '0') {
        return ['can_assign_sabak' => false, 'reason' => 'Sabqi (Juz Lesson) was not completed yesterday.'];
    }
    
    // 2. Check past 2 days of Manzil (Old Lesson) audio submissions
    $twoDaysAgo = date('Y-m-d', strtotime($classDate . ' -2 days'));
    $manzilStmt = $pdo->prepare("
        SELECT COUNT(*) FROM progress_logs 
        WHERE student_id = ? 
        AND logged_date BETWEEN ? AND ? 
        AND manzil_submitted = 0
    ");
    $manzilStmt->execute([$studentId, $twoDaysAgo, $yesterday]);
    $missingManzilCount = $manzilStmt->fetchColumn();
    
    if ($missingManzilCount >= 2) {
        return ['can_assign_sabak' => false, 'reason' => 'Manzil (Old Lesson) voice note missing for 2 consecutive days.'];
    }
    
    return ['can_assign_sabak' => true];
}
```

---

## 6. Supervisor Audit Workflow

Supervisors audit classes weekly. We capture this with the following evaluation schema:

```sql
CREATE TABLE `class_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `supervisor_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `audit_date` DATE NOT NULL,
    `camera_on` TINYINT(1) DEFAULT 1,
    `timekeeping_score` INT CHECK (`timekeeping_score` BETWEEN 1 AND 5),
    `motivation_score` INT CHECK (`motivation_score` BETWEEN 1 AND 5),
    `notes` TEXT,
    FOREIGN KEY (`supervisor_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`)
);
```

---

## 7. Lightweight Database Helper Wrapper (No ORM)

Instead of importing a heavy ORM package into `vendor/`, use standardized wrapper functions in `includes/db_helpers.php`:

```php
// Reusable, sanitised database query helpers

function db_find($pdo, $table, $id) {
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `id` = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function db_all_where($pdo, $table, $conditions = []) {
    $sql = "SELECT * FROM `{$table}`";
    $params = [];
    if (!empty($conditions)) {
        $clauses = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $sql .= " WHERE " . implode(" AND ", $clauses);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
```

---

## 📊 Development Phases & Progress Tracker

### Phase 1: Core Framework & Configuration (Done)
- [x] **Zero-Dependency DB Connection**: Replaced Composer phpdotenv with a native manual `.env` file parser.
- [x] **Laragon/Bluehost Auto-Detection**: Auto-probes local ports (3306 vs 3307) and falls back to production host (`localhost` / cPanel settings) automatically.
- [x] **Secure Environment Variables**: `.env` handles all connection details; added `.htaccess` match blocks to deny web access.
- [x] **Auth Separation**: Moved auth files to `auth/` and configured internal rewrites for clean `/login` and `/logout` URLs.
- [x] **Coordinator Modular Panel**: Split the old coordinator view into modular, individual management files (`index.php`, `courses.php`, `teachers.php`, `supervisors.php`, `students.php`).
- [x] **Standardised UI Icons**: All directories, forms, and table actions use matching Font Awesome icons (`fa-solid fa-pen-to-square` for edits, `fa-trash` for deletes, and mobile icons for contact info).

### Phase 2: Database Schema Expansion (Done)
- [x] Add `total_targets` and `target_type` columns to `courses`.
- [x] Create `progress_logs` table (tracking lessons, pages, Sabak lines, Sabqi flags, and Manzil voice note submissions).
- [x] Create `leaves` table for teachers and students.
- [x] Create `class_audits` table for supervisor evaluations.
- [x] Create `weekly_reports` table for Saturday submissions.

### Phase 3: Teacher Portal (`teacher/`) (Done)
- [x] **Class Room Dashboard**: A dashboard showing active sessions for the day, featuring the startup Duas and session timers.
- [x] **Daily Progress Logger**: Inputs to save daily attendance and progress (Lessons for Tajweed, Pages for Nazira, Sabak/Sabqi/Manzil for Hifz).
- [x] **Syllabus Progress Lock Engine**: Add query checks to prevent teachers from giving a *New Lesson (Sabak)* if the student has pending *Sabqi* or missing *Manzil* voice notes.
- [x] **Weekly Saturday Report form**: Simple form to log weekly reviews, replacing manually typed WhatsApp messages.

### Phase 4: Supervisor Portal (`supervisor/`) (Pending)
- [ ] **Audit Workspace**: A dashboard showing active classes. Supervisors can join Google Meet sessions and submit performance audit logs.
- [ ] **Leave Approval System**: Panel to check, approve, or reschedule classes for teacher/student leaves.

### Phase 5: Parent & Student Views (Done)
- [x] **Parent Progress Tracker**: Simple dashboard showing their child's current lesson/Juz, test grades, and attendance cards.
- [x] **Milestone Timeline**: Visual card showing start and end dates for each completed Juz (following the reverse 30-26 sequence for Hifz).
