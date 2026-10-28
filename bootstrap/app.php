<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 Page Expired (โทเคนความปลอดภัยของหน้าหมดอายุ — หน้าเปิดค้างนาน/เซสชันหมดอายุ) แสดงเป็นข้อความไทยที่ทำต่อได้
        // แทนหน้าขาวเขียนว่า "419 PAGE EXPIRED": หน้าล็อกอินพากลับไปล็อกอินใหม่, หน้าอื่นพากลับหน้าเดิมโดยไม่ทิ้งข้อมูลที่กรอก
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            if ($request->is('login') || ! $request->user()) {
                return redirect()->route('login')->with('status', 'หน้าล็อกอินหมดอายุเพราะเปิดค้างไว้นาน — กรุณากรอกอีเมลและรหัสผ่านอีกครั้ง');
            }

            return redirect()->back(fallback: route('dashboard'))
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'หน้านี้เปิดค้างไว้นานเกินไปจนหมดอายุ ระบบยังไม่ได้บันทึกข้อมูล กรุณากดบันทึกอีกครั้ง (ถ้ายังไม่ได้ ให้โหลดหน้านี้ใหม่)');
        });
    })->create();
