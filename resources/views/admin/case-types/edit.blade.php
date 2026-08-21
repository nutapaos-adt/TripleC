<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">แก้ไขประเภทเคส — {{ $caseType->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 rounded bg-red-50 text-red-700 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.case-types.update', $caseType) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.case-types._form')

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                            บันทึกการแก้ไข
                        </button>
                        <a href="{{ route('admin.case-types.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
