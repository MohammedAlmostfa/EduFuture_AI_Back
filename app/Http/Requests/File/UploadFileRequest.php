<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UploadFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        Log::info('UploadFileRequest - prepareForValidation', [
            'has_file' => $this->hasFile('file'),
            'file_key' => $this->file('file') ? 'exists' : 'null',
            'all_keys' => array_keys($this->all()),
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,doc,docx,txt,ppt,pptx,xls,xlsx|max:40960',
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'الملف مطلوب',
            'file.file' => 'يجب أن يكون الإدخال ملفاً',
            'file.mimes' => 'نوع الملف غير مدعوم. الأنواع المدعومة: pdf, doc, docx, txt, ppt, pptx, xls, xlsx',
            'file.max' => 'حجم الملف لا يجب أن يتجاوز 40 MB',
        ];
    }
}
