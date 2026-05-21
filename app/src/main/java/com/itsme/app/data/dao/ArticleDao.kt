package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.Article

@Dao
interface ArticleDao {
    @Query("SELECT * FROM articles WHERE travelDateId = :tid ORDER BY name")
    fun getByTravel(tid: Int): LiveData<List<Article>>

    @Query("SELECT * FROM articles WHERE travelDateId = :tid ORDER BY name")
    suspend fun getByTravelSync(tid: Int): List<Article>

    @Query("SELECT * FROM articles ORDER BY name")
    fun getAll(): LiveData<List<Article>>

    @Insert suspend fun insert(a: Article): Long
    @Update suspend fun update(a: Article)
    @Delete suspend fun delete(a: Article)
}
