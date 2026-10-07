<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The curated catalog of professions, courses, and programs users can pick from.
 * Safe to re-run: entries are matched by slug. To retire an entry, set is_active
 * to false rather than deleting it (past question sets reference it).
 */
class TopicSeeder extends Seeder
{
    public const CATALOG = [
        'Software Development' => [
            'Software Engineer', 'Frontend Developer', 'Backend Developer', 'Full-Stack Developer',
            'Laravel Developer', 'PHP Developer', 'Python Developer', 'Django Developer', 'Java Developer',
            'Spring Boot Developer', 'JavaScript Developer', 'TypeScript Developer', 'React Developer',
            'Vue.js Developer', 'Angular Developer', 'Node.js Developer', '.NET / C# Developer', 'Go Developer',
            'Ruby on Rails Developer', 'Android Developer', 'iOS Developer', 'Flutter Developer',
            'React Native Developer', 'Game Developer', 'Embedded Systems Engineer', 'QA / Software Test Engineer',
            'Test Automation Engineer', 'Software Architect', 'Technical Lead',
        ],
        'Data & AI' => [
            'Data Analyst', 'Data Scientist', 'Data Engineer', 'Machine Learning Engineer', 'AI Engineer',
            'Business Intelligence Analyst', 'Database Administrator', 'SQL Developer', 'Statistician',
            'Monitoring & Evaluation Officer',
        ],
        'Cloud, DevOps & IT' => [
            'DevOps Engineer', 'Site Reliability Engineer', 'Cloud Engineer', 'AWS Solutions Architect',
            'Azure Administrator', 'Google Cloud Engineer', 'Linux System Administrator', 'Windows System Administrator',
            'Network Engineer', 'IT Support Technician', 'IT Manager',
        ],
        'Cybersecurity' => [
            'Cybersecurity Analyst', 'Security Engineer', 'Penetration Tester', 'SOC Analyst',
            'Information Security Officer', 'Digital Forensics Analyst',
        ],
        'Healthcare' => [
            'Registered Nurse', 'Enrolled Nurse', 'Midwife', 'Clinical Officer', 'Medical Doctor (General Practitioner)',
            'Pharmacist', 'Pharmaceutical Technician', 'Medical Laboratory Scientist', 'Radiographer',
            'Physiotherapist', 'Occupational Therapist', 'Dentist', 'Dental Therapist', 'Nutritionist / Dietitian',
            'Public Health Officer', 'Environmental Health Officer', 'Community Health Worker', 'Paramedic / EMT',
            'Health Records & Information Officer', 'Optometrist', 'Clinical Psychologist', 'Hospital Administrator',
        ],
        'Education' => [
            'Pre-Primary / Early Childhood Teacher', 'Primary School Teacher', 'Secondary School Mathematics Teacher',
            'Secondary School Science Teacher', 'Secondary School English Teacher', 'Secondary School History Teacher',
            'Secondary School Geography Teacher', 'Kiswahili Teacher', 'ICT / Computer Studies Teacher',
            'Special Needs Education Teacher', 'School Head Teacher / Principal', 'Academic Master / Dean of Studies',
            'School Bursar', 'Curriculum Developer', 'University Lecturer', 'Education Officer', 'School Counselor',
            'Librarian',
        ],
        'Business & Finance' => [
            'Accountant', 'Auditor', 'Internal Auditor', 'Financial Analyst', 'Bookkeeper', 'Tax Consultant',
            'Bank Teller', 'Relationship Manager (Banking)', 'Credit / Loan Officer', 'Microfinance Officer',
            'Insurance Underwriter', 'Actuarial Analyst', 'Investment Analyst', 'Risk & Compliance Officer',
            'Business Analyst', 'Project Manager', 'Product Manager', 'Operations Manager', 'Management Consultant',
            'Entrepreneur / Small Business Owner',
        ],
        'Procurement, Supply Chain & Logistics' => [
            'Procurement Officer', 'Supply Chain Officer', 'Logistics Coordinator', 'Store Keeper / Inventory Controller',
            'Warehouse Manager', 'Clearing & Forwarding Agent', 'Fleet Manager',
        ],
        'Sales, Marketing & Communications' => [
            'Sales Representative', 'Sales Manager', 'Digital Marketing Specialist', 'Social Media Manager',
            'SEO Specialist', 'Content Writer', 'Brand Manager', 'Public Relations Officer', 'Customer Service Representative',
            'Call Center Agent', 'Journalist', 'Translator / Interpreter',
        ],
        'Human Resources & Administration' => [
            'Human Resources Officer', 'Recruiter / Talent Acquisition Specialist', 'Payroll Officer',
            'Training & Development Officer', 'Administrative Assistant', 'Executive Assistant', 'Office Manager',
            'Receptionist', 'Records Management Officer',
        ],
        'Engineering & Construction' => [
            'Civil Engineer', 'Structural Engineer', 'Electrical Engineer', 'Mechanical Engineer', 'Chemical Engineer',
            'Telecommunications Engineer', 'Mining Engineer', 'Petroleum Engineer', 'Quantity Surveyor', 'Land Surveyor',
            'Architect', 'Construction Site Supervisor', 'Health & Safety (HSE) Officer', 'Electrician', 'Plumber',
            'Welder / Fabricator', 'Motor Vehicle Mechanic', 'Draughtsperson / CAD Technician',
        ],
        'Law, Government & Social Services' => [
            'Lawyer / Advocate', 'Paralegal / Legal Assistant', 'Magistrate / Judge', 'Police Officer',
            'Immigration Officer', 'Customs Officer', 'Public Administrator', 'Social Worker',
            'Community Development Officer', 'NGO Program Officer',
        ],
        'Agriculture & Environment' => [
            'Agricultural Extension Officer', 'Agronomist', 'Veterinary Doctor', 'Livestock Officer',
            'Fisheries Officer', 'Forestry Officer', 'Environmental Officer', 'Food Scientist / Quality Assurance Officer',
        ],
        'Hospitality, Tourism & Creative' => [
            'Chef / Cook', 'Hotel Front Office Agent', 'Housekeeping Supervisor', 'Food & Beverage Manager',
            'Tour Guide', 'Travel Consultant', 'Flight Attendant', 'Graphic Designer', 'UI/UX Designer',
            'Photographer / Videographer', 'Video Editor', 'Fashion Designer', 'Interior Designer',
        ],
        'Certifications' => [
            'AWS Certified Cloud Practitioner', 'AWS Certified Solutions Architect – Associate',
            'Microsoft Azure Fundamentals (AZ-900)', 'Google Cloud Associate Cloud Engineer', 'Cisco CCNA',
            'CompTIA A+', 'CompTIA Network+', 'CompTIA Security+', 'Certified Ethical Hacker (CEH)', 'CISSP',
            'Certified Kubernetes Administrator (CKA)', 'PMP (Project Management Professional)', 'PRINCE2',
            'Scrum Master (PSM / CSM)', 'ITIL 4 Foundation', 'CPA (Certified Public Accountant)', 'ACCA',
            'CFA Level I', 'CIPS (Procurement & Supply)', 'SHRM / CIPD (Human Resources)',
        ],
        'Courses & Study Programs' => [
            'Computer Science (Undergraduate)', 'Information Technology (Diploma)', 'Software Engineering (Undergraduate)',
            'Web Development Bootcamp', 'Data Science Bootcamp', 'Cybersecurity Bootcamp', 'UI/UX Design Course',
            'Digital Marketing Course', 'Nursing (Diploma)', 'Nursing (BSc)', 'Clinical Medicine (Diploma)',
            'Pharmacy (Diploma)', 'Medicine (MD)', 'Public Health (MPH)', 'Business Administration (MBA)',
            'Accounting & Finance (Undergraduate)', 'Economics (Undergraduate)', 'Law (LLB)', 'Education (Diploma in Teaching)',
            'Education (BEd)', 'Civil Engineering (Undergraduate)', 'Electrical Engineering (Undergraduate)',
            'Agriculture (Undergraduate)', 'Procurement & Logistics (Diploma)', 'Mathematics (A-Level)',
            'Physics (A-Level)', 'Chemistry (A-Level)', 'Biology (A-Level)', 'English Language Proficiency (IELTS / TOEFL)',
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $category => $names) {
            foreach ($names as $name) {
                Topic::updateOrCreate(
                    ['slug' => Str::slug(str_replace(['/', '+', '#', '.'], [' ', ' plus ', ' sharp ', ' dot '], $name))],
                    ['name' => $name, 'category' => $category],
                );
            }
        }
    }
}
