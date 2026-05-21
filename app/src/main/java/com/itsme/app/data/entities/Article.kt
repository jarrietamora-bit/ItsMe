package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "articles")
data class Article(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val name: String,
    val store: String,
    val costPrice: Double,
    val salePrice: Double,
    val photoPath: String? = null,
    val travelDateId: Int = 0
)
