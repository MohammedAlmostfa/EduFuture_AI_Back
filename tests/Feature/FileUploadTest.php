<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_file_with_valid_token()
    {
        // إنشاء ملف اختبار
        $file = UploadedFile::fake()->create('test.pdf', 100);

        // الإرسال
        $response = $this->postJson('/api/files/upload', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer FAKE_TOKEN'
        ]);

        // التحقق
        $response->assertStatus(200);
    }

    public function test_upload_empty_file()
    {
        $file = UploadedFile::fake()->create('test.txt', 0);

        $response = $this->postJson('/api/files/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_unsupported_file_type()
    {
        $file = UploadedFile::fake()->create('test.zip', 100);

        $response = $this->postJson('/api/files/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }
}
