package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "wish_list_items")
data class WishListItem(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val headerId: Int,
    val name: String,
    val photoPath: String? = null
)
