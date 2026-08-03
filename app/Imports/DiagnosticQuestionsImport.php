<?php

namespace App\Imports;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DiagnosticQuestionsImport implements ToCollection, WithHeadingRow
{
    /**
    * Menerima seluruh baris data Excel.
    * Format Kolom Excel yang dibutuhkan:
    * question_text | category | order_number | option_text | score_weight | weakness_tag | strength_tag
    */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            $currentQuestionText = null;
            $currentQuestionId = null;

            foreach ($rows as $row) {
                // Skip baris jika kosong
                if (!isset($row['question_text']) && !isset($row['option_text'])) {
                    continue; 
                }

                $questionText = trim(strip_tags($row['question_text']));

                // Jika kolom pertanyaan ada isinya, buat pertanyaan baru
                if (!empty($questionText)) {
                    $question = DiagnosticQuestion::create([
                        'question_text' => $questionText,
                        'category' => trim(strip_tags($row['category'] ?? 'general')),
                        'is_active' => true,
                        'order_number' => $row['order_number'] ?? 0,
                    ]);
                    
                    $currentQuestionId = $question->id;
                    $currentQuestionText = $questionText;
                }

                // Masukkan opsi jawaban ke pertanyaan yang sedang aktif (terakhir dibuat)
                if ($currentQuestionId && !empty($row['option_text'])) {
                    DiagnosticOption::create([
                        'diagnostic_question_id' => $currentQuestionId,
                        'option_text' => trim(strip_tags($row['option_text'])),
                        'score_weight' => (int) ($row['score_weight'] ?? 0),
                        'weakness_tag' => !empty($row['weakness_tag']) ? trim(strip_tags($row['weakness_tag'])) : null,
                        'strength_tag' => !empty($row['strength_tag']) ? trim(strip_tags($row['strength_tag'])) : null,
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            // Lemparkan exception agar tertangkap oleh blok try-catch di Controller
            throw new \Exception('Kesalahan pada format Excel: ' . $e->getMessage());
        }
    }
}