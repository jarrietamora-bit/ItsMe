package com.itsme.app.data
import android.content.Context
import androidx.room.*
import androidx.sqlite.db.SupportSQLiteDatabase
import com.itsme.app.data.dao.*
import com.itsme.app.data.entities.*
import kotlinx.coroutines.*

@Database(entities = [User::class, Client::class, Article::class, TravelDate::class,
    WishListHeader::class, WishListItem::class, Order::class, OrderItem::class],
    version = 1, exportSchema = false)
abstract class AppDatabase : RoomDatabase() {
    abstract fun userDao(): UserDao
    abstract fun clientDao(): ClientDao
    abstract fun articleDao(): ArticleDao
    abstract fun travelDateDao(): TravelDateDao
    abstract fun wishListDao(): WishListDao
    abstract fun orderDao(): OrderDao

    companion object {
        @Volatile private var INSTANCE: AppDatabase? = null
        fun getDatabase(context: Context): AppDatabase = INSTANCE ?: synchronized(this) {
            Room.databaseBuilder(context.applicationContext, AppDatabase::class.java, "itsme_db")
                .addCallback(object : Callback() {
                    override fun onCreate(db: SupportSQLiteDatabase) {
                        super.onCreate(db)
                        CoroutineScope(Dispatchers.IO).launch {
                            INSTANCE?.userDao()?.insert(User(username = "admin", password = "admin123", isAdmin = true))
                        }
                    }
                }).build().also { INSTANCE = it }
        }
    }
}
