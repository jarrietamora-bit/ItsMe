package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.Client

@Dao
interface ClientDao {
    @Query("SELECT * FROM clients ORDER BY fullName")
    fun getAllClients(): LiveData<List<Client>>

    @Query("SELECT * FROM clients ORDER BY fullName")
    suspend fun getAllSync(): List<Client>

    @Insert suspend fun insert(c: Client): Long
    @Update suspend fun update(c: Client)
    @Delete suspend fun delete(c: Client)
}
