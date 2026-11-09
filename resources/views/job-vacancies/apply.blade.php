<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ $jobVacancy->title }} Apply
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-black shadow-lg rounded-lg p-6 max-w-7xl mx-auto">
            <a href="{{ route('job-vacancies.show', $jobVacancy->id) }}"
                class="text-blue-400 hover:underline mb-6 inline-block">
                ← Back
            </a>

            <div class="space-y-6">
                <!-- الهيدر الرئيسي للمعلومات -->
                <div class="border-b border-white/10 pb-6">
                    <div class="flex flex-col gap-1">
                        <!--عنوان الوظيفة -->
                        <h1 class="text-2xl font-bold text-white">{{ $jobVacancy->title }}</h1>

                        <!-- اسم الشركة تحت العنوان مباشرة -->
                        <p class="text-gray-400 font-medium">{{ $jobVacancy->company->name }}</p>

                        <!-- اللوكيشن، السالاري، والتايب جنب بعض في سطر واحد -->
                        <div class="flex items-center gap-2 text-gray-400 text-sm mt-1">
                            <span>{{ $jobVacancy->location }}</span>
                            <span>•</span>
                            <span>{{'$' . number_format($jobVacancy->salary) }}</span>
                            <span>•</span>
                            <span
                                class="capitalize bg-indigo-500 text-white p-2 rounded-lg">{{ $jobVacancy->type }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full mt-6">
                <form action="{{ route('job-vacancies.store', $jobVacancy->id) }}" method="post"
                    enctype="multipart/form-data" class="w-full space-y-6">
                    @csrf

                    @if ($errors->any())
                        <div class="bg-red-500 text-white p-4 rounded-lg">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Resume Selection -->
                    <div>
                        <h3 class="text-xl font-semibold text-white mb-4">Choose Your Resume</h3>

                        <div class="mb-6">
                            <x-input-label for="resume" value="Select from your existing resumes :" />

                            <!-- List of Resumes -->
                            <div class="space-y-4">
                                @forelse ($resumes as $resume)
                                    <div class="flex items-center gap-4">
                                        <input type="radio" name="resume_option" id="{{ $resume->id }}"
                                            value="{{ $resume->id }}" @class([
                                                'border-red-500' => $errors->has('resume_option'),
                                                'border-gray-600' => !$errors->has('resume_option'),
                                            ])>

                                        <x-input-label for="existing_{{ $resume->id }}" class="text-white cursor-pointer">
                                            {{ $resume->filename }}
                                            <span class="text-gray-400 text-sm">(Last updated
                                                {{ $resume->updated_at->format('M d, Y') }})</span>
                                        </x-input-label>
                                    </div>
                                @empty
                                    <span class="text-gray-400 text-sm">No resumes found.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Upload Resume -->
                    <div x-data="{ fileName: '' }">
                        <div class="flex items-center gap-2">
                            <input x-ref="newResumeRadio" type="radio" name="resume_option" id="new_resume" value="new_resume" @class([
                                'border-red-500' => $errors->has('resume_option'),
                                'border-gray-600' => !$errors->has('resume_option'),
                            ])>
                            <x-input-label class="text-white cursor-pointer" for="new_resume"
                                value="upload a new resume :" />
                        </div>
                        <div class="flex items-center mt-4">
                            <div class="flex-1">
                                <label for="new_resume_file" class="block text-white cursor-pointer">
                                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-4 hover:border-blue-500 transition"
                                        :class="{'border-blue-500' : fileName , 'border-red-500' : {{ $errors->has('resume_file') ? 'true' : 'false' }} }">
                                        <input @change="fileName = $event.target.files[0].name; $refs.newResumeRadio.checked = true" type="file"
                                            name="resume_file" id="new_resume_file" class="hidden" accept=".pdf">
                                        <div class="text-center">
                                            <template x-if="!fileName">
                                                <p class="text-gray-400">Click to upload PDF (Max 5MB)</p>
                                            </template>

                                            <template x-if="fileName">
                                                <div>
                                                    <p x-text="fileName" class="mt-2 text-blue-400"></p>
                                                    <p class="text-gray-400 text-sm mt-1">Click to change file</p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="w-full mt-6">
                        <x-primary-button class="w-full justify-center">
                            Apply Now
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>