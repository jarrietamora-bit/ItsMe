package com.itsme.app.data

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.sqlite.db.SupportSQLiteDatabase
import com.itsme.app.data.dao.ArticleDao
import com.itsme.app.data.dao.ClientDao
import com.itsme.app.data.dao.OrderDao
import com.itsme.app.data.dao.TravelDateDao
import com.itsme.app.data.dao.UserDao
import com.itsme.app.data.dao.WishListDao
import com.itsme.app.data.entities.Article
import com.itsme.app.data.entities.Client
import com.itsme.app.data.entities.Order
import com.itsme.app.data.entities.OrderItem
import com.itsme.app.data.entities.TravelDate
import com.itsme.app.data.entities.User
import com.itsme.app.data.entities.WishListHeader
import com.itsme.app.data.entities.WishListItem

@Database(
    entities = [
        User::class,
        Client::class,
        Article::class,
        TravelDate::class,
        WishListHeader::class,
        WishListItem::class,
        Order::class,
        OrderItem::class
    ],
    version = 1,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {

    abstract fun userDao(): UserDao
    abstract fun clientDao(): ClientDao
    abstract fun articleDao(): ArticleDao
    abstract fun travelDateDao(): TravelDateDao
    abstract fun wishListDao(): WishListDao
    abstract fun orderDao(): OrderDao

    private class AdminUserCallback : RoomDatabase.Callback() {
        override fun onCreate(db: SupportSQLiteDatabase) {
            super.onCreate(db)
            db.execSQL("INSERT INTO users (username, password, isAdmin) VALUES ('admin', 'admin123', 1)")
        }
    }

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "itsme_db"
                ).addCallback(AdminUserCallback()).build()
                INSTANCE = instance
                instance
            }
        }
    }
}
