package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "wish_list_headers")
data class WishListHeader(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val listName: String,
    val clientId: Int,
    val travelDateId: Int
)
