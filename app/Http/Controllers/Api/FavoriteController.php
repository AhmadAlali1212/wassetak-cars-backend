<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoriteController extends Controller
{
    /**
     * عرض قائمة السيارات المفضّلة للمستخدم الحالي.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $favorites = $request->user()
            ->favorites()
            ->with(['car' => function ($query) {
                $query->with(['ad', 'rentalAd', 'auction']);
            }])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'message' => 'تم جلب المفضلة بنجاح.',
            'favorites' => $favorites,
        ]);
    }

    /**
     * إضافة سيارة للمفضلة.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'car_id' => 'required|integer|exists:cars,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $car = Car::find($request->car_id);

        if ((int) $car->user_id === (int) $user->id) {
            return response()->json([
                'message' => 'لا يمكنك إضافة سيارتك الخاصة للمفضلة.',
            ], 422);
        }

        $favorite = $user->favorites()->firstOrCreate([
            'car_id' => $car->id,
        ]);

        return response()->json([
            'message' => '✅ تمت إضافة السيارة للمفضلة.',
            'favorite' => $favorite,
            'is_favorite' => true,
        ], 201);
    }

    /**
     * إزالة سيارة من المفضلة.
     */
    public function destroy(Request $request, $carId)
    {
        $deleted = $request->user()
            ->favorites()
            ->where('car_id', $carId)
            ->delete();

        return response()->json([
            'message' => $deleted
                ? '✅ تمت إزالة السيارة من المفضلة.'
                : 'السيارة غير موجودة في مفضلتك أصلاً.',
            'is_favorite' => false,
        ]);
    }

    /**
     * التحقق السريع: هل هذه السيارة ضمن مفضلة المستخدم الحالي؟
     */
    public function status(Request $request, $carId)
    {
        $isFavorite = $request->user()
            ->favorites()
            ->where('car_id', $carId)
            ->exists();

        return response()->json([
            'car_id' => (int) $carId,
            'is_favorite' => $isFavorite,
        ]);
    }
}
