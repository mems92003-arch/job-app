<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyJobRequest;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Resume;
use App\Services\ResumeAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Pest\Exceptions\FileOrFolderNotFound;
use OpenAI\Laravel\Facades\OpenAI;

class JobVacancyController extends Controller
{
    protected ResumeAnalysisService $resumeAnalysisService;

    public function __construct(ResumeAnalysisService $resumeAnalysisService)
    {
        $this->resumeAnalysisService = $resumeAnalysisService;
    }

    public function show(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        return view('job-vacancies.show', compact('jobVacancy'));
    }

    public function apply(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $resumes = Auth::user()->resumes;
        return view('job-vacancies.apply', compact('jobVacancy', 'resumes'));
    }

    public function store(ApplyJobRequest $request, string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $resumeId = null;
        $extractedInfo = null;

        if ($request->input('resume_option') === 'new_resume') {
            // 1. التحقق من وجود الملف باسمه الصحيح في الـ Request
            if (!$request->hasFile('resume_file')) {
                return back()->withErrors(['resume_file' => 'يرجى إرفاق ملف الـ CV قبل الإرسال.']);
            }

            $file = $request->file('resume_file');
            $originalFileName = $file->getClientOriginalName();

            // 2. رفع الملف مباشرة إلى مجلد Resumes على S3 / Supabase
            $path = $file->store('Resumes', 's3');

            // 3. تحليل الـ PDF واستخراج البيانات مرة واحدة فقط باستخدام الـ Path المرفوع
            $extractedInfo = $this->resumeAnalysisService->extractResumeInformation($path);

            // 4. إنشاء سجل الـ Resume في قاعدة البيانات
            $resume = Resume::create([
                'filename' => $originalFileName,
                'fileUrl' => $path,
                'userId' => Auth::id(),
                'contactDetails' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'summary' => $extractedInfo['summary'],
                'skills' => $extractedInfo['skills'],
                'experience' => $extractedInfo['experience'],
                'education' => $extractedInfo['education'],
            ]);

            $resumeId = $resume->id;

        } else {
            // في حالة اختيار CV موجود مسبقاً
            $resumeId = $request->input('resume_option');
            $resume = Resume::findOrFail($resumeId);

            $extractedInfo = [
                'summary' => $resume->summary,
                'skills' => $resume->skills,
                'experience' => $resume->experience,
                'education' => $resume->education,
            ];
        }

        $evalution = $this->resumeAnalysisService->analyzeResume($jobVacancy, $extractedInfo);

        // 5. إنشاء طلب التقديم (Job Application)
        JobApplication::create([
            'jobVacancyId' => $id,
            'status' => 'pending',
            'resumeId' => $resumeId,
            'userId' => Auth::id(),
            'aiGeneratedScore' => $evalution['aiGeneratedScore'],
            'aiGeneratedFeedback' => $evalution['aiGeneratedFeedback'],
        ]);

        return redirect()->route('job-applications.index', $id)
            ->with('success', 'Application submitted successfully!');
    }

}
