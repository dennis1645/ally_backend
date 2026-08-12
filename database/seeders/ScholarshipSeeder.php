<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScholarshipSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('scholarships')->insert([

            [
                'name' => 'LPDP Scholarship',

                'provider_country' =>
                    'Indonesian Government - Indonesia',

                'description' =>
                    'Government-funded scholarship supporting Indonesians pursuing postgraduate studies at top universities.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'Indonesian citizens applying for Master degree programs. Leadership experience and community service are required. Minimum IELTS 6.5 or accepted TOEFL equivalent.',

                'application_process' =>
                    'Submit the LPDP scholarship application through the official LPDP application system during the application period.',

                'benefits' =>
                    'Full tuition, Living allowance, Research funding',

                'official_website' =>
                    'https://lpdp.kemenkeu.go.id',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://lpdp.kemenkeu.go.id',

                'deadline_date' =>
                    '2026-07-31',

                'status' =>
                    'published',

                'notes' =>
                    'Profile tags: leadership, community service, social impact. Application period: 30 June - 31 July 2026.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'DAAD EPOS Scholarship',

                'provider_country' =>
                    'DAAD - Germany',

                'description' =>
                    'German government scholarship for development-related postgraduate programs.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'International students applying for eligible Master programs in development-related fields. Minimum 2 years of relevant work experience is required.',

                'application_process' =>
                    'Applications are submitted through participating universities and DAAD EPOS application procedures.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://www.daad.de',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.daad.de',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Fields: Development Studies, Engineering, Economics, Public Policy, Environmental Sciences. Application period varies by university, typically August-October. Profile tags: professional experience, development, career advancement.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'Australia Awards Scholarship',

                'provider_country' =>
                    'Australian Government - Australia',

                'description' =>
                    "Australia's flagship international development scholarship.",

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'Applicants from eligible developing countries applying for Master programs in priority development fields. Leadership experience is required.',

                'application_process' =>
                    'Apply through the Australia Awards application process for the relevant country and intake.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://www.australiaawardsindonesia.org',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.australiaawardsindonesia.org',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application period usually February-April. Profile tags: leadership, public service, development.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'Chevening Scholarship',

                'provider_country' =>
                    'UK Government - United Kingdom',

                'description' =>
                    'UK Government scholarship for future global leaders.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'Applicants from Chevening-eligible countries applying for Master degree programs. Minimum 2 years of eligible work experience and leadership potential are required.',

                'application_process' =>
                    'Submit an application through the official Chevening application platform during the annual application period.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://www.chevening.org',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.chevening.org',

                'deadline_date' =>
                    '2026-10-06',

                'status' =>
                    'published',

                'notes' =>
                    'Application period: 4 August - 6 October 2026. Profile tags: leadership, professional experience, networking.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'Fulbright Scholarship',

                'provider_country' =>
                    'U.S. Government - United States',

                'description' =>
                    'Prestigious U.S. government scholarship promoting educational exchange.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'Eligibility depends on the applicant country. Applicants should demonstrate academic achievement, leadership potential and relevant academic or professional experience.',

                'application_process' =>
                    'Apply through the Fulbright program applicable to the applicant country.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://foreign.fulbrightonline.org',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://foreign.fulbrightonline.org',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application period usually February-May depending on country. Fields: all fields except clinical medicine. Profile tags: research, academic excellence, leadership.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'Türkiye Scholarships',

                'provider_country' =>
                    'Government of Türkiye - Türkiye',

                'description' =>
                    'Fully funded Turkish government scholarship including tuition, accommodation, stipend and airfare.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'International students applying for eligible Master programs. Leadership and academic excellence are valued.',

                'application_process' =>
                    'Apply through the official Türkiye Scholarships application system during the annual application period.',

                'benefits' =>
                    'Full tuition, Accommodation, Monthly stipend, Airfare',

                'official_website' =>
                    'https://turkiyeburslari.gov.tr',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://turkiyeburslari.gov.tr',

                'deadline_date' =>
                    '2026-02-20',

                'status' =>
                    'published',

                'notes' =>
                    'Application period: 10 January - 20 February. Profile tags: academic excellence, leadership, global perspective.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'UAE Government Scholarship',

                'provider_country' =>
                    'Various UAE Universities & Government - United Arab Emirates',

                'description' =>
                    'Scholarships offered by UAE government and leading universities such as MBZUAI, Khalifa University and UAE University.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'International students applying for Master programs in Engineering, Science, Business, Artificial Intelligence or Medicine. Minimum IELTS 6.0 where applicable.',

                'application_process' =>
                    'Application process varies by university and scholarship provider.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://www.moe.gov.ae',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.moe.gov.ae',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application deadline varies by university. Profile tags: academic excellence, research, innovation.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'U.S. University Scholarships',

                'provider_country' =>
                    'Individual Universities - United States',

                'description' =>
                    'Merit-based scholarships offered by U.S. universities.',

                'funding_type' =>
                    'partial_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'Eligibility and requirements vary by university. Generally intended for outstanding students and applicants with strong academic records.',

                'application_process' =>
                    'Apply directly to participating U.S. universities according to their individual scholarship and admission procedures.',

                'benefits' =>
                    null,

                'official_website' =>
                    'https://educationusa.state.gov',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://educationusa.state.gov',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application deadline varies by university. Profile tags: academic excellence, high GPA.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'MEXT Scholarship',

                'provider_country' =>
                    'Government of Japan - Japan',

                'description' =>
                    'Japanese government scholarship covering tuition, living allowance and airfare.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'International students applying for eligible Master programs. Strong academic records and research orientation are preferred.',

                'application_process' =>
                    'Apply through the Japanese Embassy recommendation or university recommendation route, depending on the program.',

                'benefits' =>
                    'Full tuition, Monthly allowance, Airfare',

                'official_website' =>
                    'https://www.studyinjapan.go.jp',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.studyinjapan.go.jp',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application period usually April-May for Embassy recommendation. Profile tags: research, academic excellence, innovation.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],


            [
                'name' => 'Global Korea Scholarship',

                'provider_country' =>
                    'Korean Government - South Korea',

                'description' =>
                    'Fully funded Korean Government scholarship including tuition, stipend and airfare.',

                'funding_type' =>
                    'fully_funded',

                'degree_level' =>
                    'master',

                'start_date' =>
                    null,

                'eligibility_criteria' =>
                    'International students applying for eligible Master programs. Outstanding academic performance and research potential are valued.',

                'application_process' =>
                    'Apply through the Korean Embassy track or university track during the annual GKS application period.',

                'benefits' =>
                    'Full tuition, Monthly stipend, Airfare',

                'official_website' =>
                    'https://www.studyinkorea.go.kr',

                'contact_email' =>
                    null,

                'contact_phone' =>
                    null,

                'application_link' =>
                    'https://www.studyinkorea.go.kr',

                'deadline_date' =>
                    null,

                'status' =>
                    'published',

                'notes' =>
                    'Application period usually February-March for Embassy track. Profile tags: research, academic excellence, global mindset.',

                'image_url' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),

                'deleted_at' =>
                    null,
            ],

        ]);
    }
}