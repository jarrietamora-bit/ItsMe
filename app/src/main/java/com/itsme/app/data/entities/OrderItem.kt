package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "order_items")
data class OrderItem(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val orderId: Int,
    val articleId: Int,
    val articleName: String,
    val quantity: Int = 1,
    val salePrice: Double
)
