<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\PdfToText\Pdf;

class ResumeAnalysisService
{
    public function extractResumeInformation(string $fileUrl)
    {
        try {
            // Extract raw text from the resume pdf file (read pdf file, and get the text)
            $rawText = $this->extractTextFromPdf($fileUrl);

            Log::debug('Successfully extracted text from pdf file ' . strlen($rawText) . ' characters');

            // Use OpenAI API to organize the text into a structure formt
            $response = OpenAI::chat()->create([
                'model' => 'qwen/qwen3.8-27b',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a percise resume parser. Extract information exactly as it appears in the resume without adding any interpretation or additonal information. tha output should be in JSON format.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Parse the following resume content and extract the information as a JSON Object with the exact keys: 'summary', 'skills', 'experience'. 'education'. The resume content is: {$rawText}. Return an empty string for key that is not found "
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.1
            ]);

            $result = $response->choices[0]->message->content;
            Log::debug('OpenAI response: ' . $result);

            $parsedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse OpenAI response: ' . json_last_error_msg());
                throw new \Exception('Failed to parse openAI response');
            }

            // validate the parsed result
            $requiredKeys = ['summary', 'skills', 'experience', 'education'];
            $missingKeys = array_diff($requiredKeys, array_keys($parsedResult));

            if (count($missingKeys) > 0) {
                Log::error('Missing required keys: ' . implode(', ', $missingKeys));
                throw new \Exception('Missing required key in the parsed result');
            }
            // Output: summary, skills, experience, education -> JSON  

            // Return the JSON object
            return [
                'summary' => $parsedResult['summary'] ?? '',
                'skills' => $parsedResult['skills'] ?? '',
                'experience' => $parsedResult['experience'] ?? '',
                'education' => $parsedResult['education'] ?? ''
            ];
        } catch (\Exception $e) {
            Log::error('Error extracting resume information: ' . $e->getMessage());
            return [
                'summary' => '',
                'skills' => '',
                'experience' => '',
                'education' => ''
            ];

        }
    }

    public function analyzeResume(object $jobVacancy, array $resumeData)
    {
        try {
            $jobDetails = json_encode([
                'job_title' => $jobVacancy->title,
                'job_description' => $jobVacancy->description,
                'job_location' => $jobVacancy->location,
                'job_type' => $jobVacancy->type,
                'job_salary' => $jobVacancy->salary
            ]);

            $resumeDetails = json_encode($resumeData);

            $response = OpenAI::chat()->create([
                'model' => 'qwen/qwen3.8-27b',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are an expert HR professional and job recruiter.
                    Your task is to analyze the resume and determine if the candidate is a good fit for the job.
                    The output must be JSON format with exactly two keys: 'aiGeneratedScore' (integer from 0 to 100) and 'aiGeneratedFeedback' (a single string containing detailed feedback text).
                    Do NOT wrap 'aiGeneratedFeedback' in an array or nested object; it must be a plain text string."
                    ],
                    [
                        'role' => 'user',
                        'content' => "Please evaluate this job application. Job Details: {$jobDetails}. Resume Details: {$resumeDetails}"
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.1
            ]);

            $result = $response->choices[0]->message->content;
            Log::debug('OpenAI evaluation response: ' . $result);

            $parsedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse OpenAI response: ' . json_last_error_msg());
                throw new \Exception('Failed to parse OpenAI response');
            }

            if (!isset($parsedResult['aiGeneratedScore']) || !isset($parsedResult['aiGeneratedFeedback'])) {
                Log::error('Missing required keys in the parsed result');
                throw new \Exception('Missing required keys in the parsed result');
            }

            $score = $parsedResult['aiGeneratedScore'];
            $feedback = $parsedResult['aiGeneratedFeedback'];

            // إذا أرجع الذكاء الاصطناعي الفيدباك كمصفوفة أو كائن، نحوله إلى النص المطلق
            if (is_array($feedback)) {
                $feedback = json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            return [
                'aiGeneratedScore' => (int) $score,
                'aiGeneratedFeedback' => (string) $feedback
            ];

        } catch (\Exception $e) {
            Log::error('Error analyzing resume: ' . $e->getMessage());
            return [
                'aiGeneratedScore' => 0,
                'aiGeneratedFeedback' => 'Error occurred during analysis: ' . $e->getMessage()
            ];
        }
    }

    private function extractTextFromPdf(string $fileUrl): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'resume');

        $filePath = parse_url($fileUrl, PHP_URL_PATH) ?? $fileUrl;
        $storagePath = ltrim($filePath, '/');

        // إذا كان الرابط يحتوي على مسار لوحة التحكم مثل buckets/resumes/filename.pdf
        if (str_contains($storagePath, 'buckets/')) {
            $storagePath = \Illuminate\Support\Str::after($storagePath, 'buckets/');
        }

        // إذا لم يجد الملف، نجرب البحث عنه داخل مجلد resumes فقط إذا كان $storagePath هو اسم الملف فقط
        if (!Storage::disk('s3')->exists($storagePath)) {
            $filename = basename($storagePath);
            $altPath = "resumes/{$filename}";

            if (Storage::disk('CVs')->exists($altPath)) {
                $storagePath = $altPath;
            } else {
                throw new \Exception("File not found on S3 bucket at path: [{$storagePath}]");
            }
        }

        $pdfContent = Storage::disk('s3')->get($storagePath);
        if (!$pdfContent) {
            throw new \Exception('Failed to read file');
        }

        file_put_contents($tempFile, $pdfContent);

        // Check if pdf-to-text is installed
        $pdfToTextPath = ['/opt/homebrew/bin/pdftext', '/usr/bin/pdftotext', '/usr/local/bin/pdftotext'];
        $pdfToTextAvailable = false;

        foreach ($pdfToTextPath as $path) {
            if (file_exists($path)) {
                $pdfToTextAvailable = true;
                break;
            }
        }

        if (!$pdfToTextAvailable) {
            throw new \Exception('pdf-to-text is not installed');
        }

        // Extract text from the pdf file
        $instance = new Pdf();
        $instance->setPdf($tempFile);
        $text = $instance->text();

        // Clean up the temp file
        unlink($tempFile);

        return $text;

    }
}
