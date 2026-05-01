<?php

namespace App\Services\Auth;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Events\Registered;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AuthService
{
    /**
     * Register a new user.
     *
     * This method handles user registration. It stores user data temporarily in the cache
     * and sends a verification code to the user's email.
     *
     * @param array $data User data: email, password, etc.
     * @return array Contains message, status, and additional data.
     */
    public function register($data)
    {
        try {
            // Generate a unique cache key for the user data
            $userDataKey = 'user_data_' . $data['email'];

            // Check if the user data is already cached
            if (Cache::has($userDataKey)) {
                return [
                    'status' => 400,
                    'message' => [
                        'errorDetails' => ['خطأ في عملية التسجيل، يرجى المحاولة مرة أخرى.'],
                    ],
                ];
            }

            // Store user data in cache for 1 hour
            Cache::put($userDataKey, $data, 3600);

            // Generate a unique cache key for the verification code
            $verifkey = 'verification_code_' . $data['email'];

            // Check if the verification code already exists in the cache
            if (Cache::has($verifkey)) {
                return [
                    'status' => 400,
                    'message' => [
                        'errorDetails' => ['رمز التحقيق موجود بالفعل، يرجى المحاولة لاحقاً.'],
                    ],
                ];
            }

            // Generate a random 6-digit code and store it in the cache
            $code = Cache::remember($verifkey, 3600, function () {
                return random_int(1000, 9999);
            });

            // Trigger the Registered event to send the verification email
            event(new Registered($data, $verifkey));

            // Return success response
            return [
                'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني بنجاح.',
                'status' => 201,
                'data' => [
                    'email' => $data['email'],
                ],
            ];
        } catch (Exception $e) {
            // Log the error if registration fails
            Log::error('خطأ في عملية التسجيل: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => ['حدث خطأ عام، يرجى المحاولة مرة أخرى.'],
                ],
            ];
        }
    }

    /**
     * Verify user account using the verification code.
     *
     * This method verifies the user's account using the verification code sent to their email.
     * If the code is correct, it creates the user in the database and returns a JWT token.
     *
     * @param array $data Contains email and verification code.
     * @return array Contains message, status, and additional data.
     */
    public function verficationacount($data)
    {
        try {

            $userDataKey = 'user_data_' . $data['email'];
            $userData = Cache::get($userDataKey);

            if (!$userData) {
                return [
                    'status' => 404,
                    'message' => [
                        'errorDetails' => ['لم تقم بالتسجيل بعد، يرجى التسجيل أولاً.'],
                    ],
                ];
            }
            // Generate the cache key for the verification code
            $verifkey = 'verification_code_' . $data['email'];

            // Retrieve the cached verification code
            $cachedCode = Cache::get($verifkey);

            // Check if the provided code matches the cached code
            if ($cachedCode == $data['code']) {
                // Retrieve the user data from cache

                // Create the user in the database
                $user = User::create([
                    'email' => $userData['email'],
                    'name' => $userData['name'] ,
                    'password' => bcrypt($userData['password']), // Hash the password
                    'email_verified_at' => now(), // Mark the email as verified
                ]);

                // Generate a JWT token for the user
                $token = JWTAuth::fromUser($user);

                // Clear the verification code and user data from the cache
                Cache::forget($verifkey);
                Cache::forget($userDataKey);

                // // Fetch all cities
                // $cities = Cache::rememberForever('cities_list', function () {
                //     return City::select('id', 'city_name')->get();
                // });
                return [
                    'message' => 'تم التحقق من بريدك الإلكتروني وتسجيل حسابك بنجاح.',
                    'status' => 200,
                    'data' => [
                        'token' => $token, // Return the generated token
                     //   'cities' => $cities, // Return cities for new users
                    ],
                ];
            } else {
                return [
                    'status' => 400,
                    'message' => [
                        'errorDetails' => ['رمز التحقق غير صحيح، يرجى المحاولة مرة أخرى.'],
                    ],
                ];
            }
        } catch (Exception $e) {
            // Log the error
            Log::error('خطأ في التحقق من الحساب: ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => ['حدث خطأ عام، يرجى المحاولة مرة أخرى.'],
                ],
            ];
        }
    }

    /**
     * Resend the verification code.
     *
     * This method resends the verification code to the user's email if the previous code has expired or the user requests a new one.
     *
     * @param array $data Contains the user's email.
     * @return array Contains message, status, and additional data.
     */
    public function resendCode($data)
    {
        try {
            // Generate the cache key for the verification code
            $verifkey = 'verification_code_' . $data['email'];

            // Check if a verification code already exists in the cache
            if (Cache::has($verifkey)) {


                Cache::forget($verifkey);



                // Generate a new 6-digit random code and store it in the cache for 1 hour
                $code = Cache::remember($verifkey, 3600, function () {
                    return random_int(1000, 9999);
                });

                // Trigger the Registered event to send the new verification email
                event(new Registered($data, $verifkey));

                // Return success response
                return [
                    'message' => 'تم إعادة إرسال رمز التحقق إلى بريدك الإلكتروني بنجاح.',
                    'status' => 200,
                ];
            } else {
                // If no code exists in the cache, return an error
                return [
                    'status' => 400,
                    'message' => [
                        'errorDetails' => ['لم تقم بالتسجيل بعد، يرجى التسجيل أولاً.'],
                    ],
                ];
            }
        } catch (Exception $e) {
            // Log the error if resending the code fails
            Log::error('خطأ في إعادة إرسال الرمز: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => ['حدث خطأ عام، يرجى المحاولة مرة أخرى.'],
                ],
            ];
        }
    }


    /**
     * Login a user.
     *
     * This method authenticates a user using their email and password.
     * If successful, it returns a JWT token for further authenticated requests.
     *
     * @param array $credentials User credentials: email, password.
     * @return array Contains message, status, data, and authorization details.
     */
    public function login($credentials)
    {
        try {
            // Attempt to authenticate the user using JWT
            if (!$token = JWTAuth::attempt($credentials)) {
                // If authentication fails
                return [
                    'status' => 401,
                    'message' => [
                        'errorDetails' => ['بيانات الدخول غير صحيحة، يرجى المحاولة مرة أخرى.'],
                    ],
                ];
            } else {
                // If authentication succeeds
                $user = Auth::user();
                return [
                    'message' => 'تم تسجيل الدخول بنجاح.',
                    'status' => 201,
                    'data' => [
                        'token' => $token, // Return the generated token
                        'type' => 'bearer', // Token type
                    ],
                ];
            }
        } catch (Exception $e) {
            // Log the error if login fails
            Log::error('خطأ في تسجيل الدخول: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => 'فشلت العملية، يرجى المحاولة مرة أخرى.',
                ],
            ];
        }
    }

    /**
     * Logout the authenticated user.
     *
     * This method logs out the currently authenticated user.
     *
     * @return array Contains message and status.
     */
    public function logout()
    {
        try {
            // Logout the user
            Auth::logout();
            return [
                'message' => 'تم تسجيل الخروج بنجاح.',
                'status' => 200,
            ];
        } catch (Exception $e) {
            // Log the error if logout fails
            Log::error('خطأ في تسجيل الخروج: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => 'فشلت العملية، يرجى المحاولة مرة أخرى.',
                ],
            ];
        }
    }

    /**
     * Refresh the JWT token for the authenticated user.
     *
     * This method refreshes the JWT token for the authenticated user.
     *
     * @return array Contains message, status, user, and authorization details.
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();

            // Correct way to refresh the token

            // Refresh the token for the authenticated user
            return [
                'message' => 'تم تجديد التوكن بنجاح.',
                'status' => 200,
                'data' => [
                    'user' => auth()->user(), // Return the authenticated user
                    'token' => $newToken, // Return the new refreshed token
                ],
            ];
        } catch (Exception $e) {
            // Log the error if token refresh fails
            Log::error('خطأ في تجديد التوكن: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => 'فشلت العملية، يرجى المحاولة مرة أخرى.',
                ],
            ];
        }
    }
}
