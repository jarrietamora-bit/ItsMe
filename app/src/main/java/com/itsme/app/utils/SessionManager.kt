package com.itsme.app.utils
import android.content.Context

object SessionManager {
    private const val PREF = "itsme_session"
    private fun sp(ctx: Context) = ctx.getSharedPreferences(PREF, Context.MODE_PRIVATE)

    fun saveUser(ctx: Context, id: Int, username: String, isAdmin: Boolean) =
        sp(ctx).edit().putInt("uid", id).putString("uname", username).putBoolean("admin", isAdmin).apply()

    fun getUserId(ctx: Context) = sp(ctx).getInt("uid", -1)
    fun getUsername(ctx: Context) = sp(ctx).getString("uname", "") ?: ""
    fun isAdmin(ctx: Context) = sp(ctx).getBoolean("admin", false)
    fun isLoggedIn(ctx: Context) = getUserId(ctx) != -1
    fun setTravelId(ctx: Context, id: Int) = sp(ctx).edit().putInt("travel", id).apply()
    fun getTravelId(ctx: Context) = sp(ctx).getInt("travel", 0)
    fun logout(ctx: Context) = sp(ctx).edit().clear().apply()
}
