package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.User

@Dao
interface UserDao {
    @Query("SELECT * FROM users ORDER BY username")
    fun getAllUsers(): LiveData<List<User>>

    @Query("SELECT * FROM users WHERE username = :u AND password = :p LIMIT 1")
    suspend fun login(u: String, p: String): User?

    @Query("SELECT COUNT(*) FROM users")
    suspend fun count(): Int

    @Insert suspend fun insert(user: User)
    @Update suspend fun update(user: User)
    @Delete suspend fun delete(user: User)
}
