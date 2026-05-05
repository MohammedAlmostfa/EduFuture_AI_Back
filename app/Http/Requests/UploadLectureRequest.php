<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLectureRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,docx,txt|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'الرجاء رفع ملف المحاضرة',
            'file.mimes' => 'نوع الملف غير مدعوم. الأنواع المدعومة: PDF, DOCX, TXT',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }
}
