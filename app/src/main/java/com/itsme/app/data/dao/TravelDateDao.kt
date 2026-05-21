package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.TravelDate

@Dao
interface TravelDateDao {
    @Query("SELECT * FROM travel_dates ORDER BY id DESC")
    fun getAll(): LiveData<List<TravelDate>>

    @Query("SELECT * FROM travel_dates WHERE isActive = 1 LIMIT 1")
    fun getActive(): LiveData<TravelDate?>

    @Query("SELECT * FROM travel_dates WHERE isActive = 1 LIMIT 1")
    suspend fun getActiveSync(): TravelDate?

    @Query("UPDATE travel_dates SET isActive = 0") suspend fun deactivateAll()
    @Query("UPDATE travel_dates SET isActive = 1 WHERE id = :id") suspend fun activate(id: Int)
    @Query("UPDATE travel_dates SET isCompleted = 1, isActive = 0 WHERE id = :id") suspend fun complete(id: Int)

    @Insert suspend fun insert(td: TravelDate): Long
    @Update suspend fun update(td: TravelDate)
    @Delete suspend fun delete(td: TravelDate)
}
