package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.WishListHeader
import com.itsme.app.data.entities.WishListItem

@Dao
interface WishListDao {
    @Query("SELECT * FROM wish_list_headers WHERE travelDateId = :tid ORDER BY listName")
    fun getHeadersByTravel(tid: Int): LiveData<List<WishListHeader>>

    @Query("SELECT * FROM wish_list_items WHERE headerId = :hid ORDER BY name")
    fun getItems(hid: Int): LiveData<List<WishListItem>>

    @Insert suspend fun insertHeader(h: WishListHeader): Long
    @Update suspend fun updateHeader(h: WishListHeader)
    @Delete suspend fun deleteHeader(h: WishListHeader)

    @Query("DELETE FROM wish_list_items WHERE headerId = :hid")
    suspend fun deleteItemsByHeader(hid: Int)

    @Insert suspend fun insertItem(i: WishListItem): Long
    @Update suspend fun updateItem(i: WishListItem)
    @Delete suspend fun deleteItem(i: WishListItem)
}
