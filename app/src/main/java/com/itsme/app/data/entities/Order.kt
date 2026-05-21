package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "orders")
data class Order(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val clientId: Int,
    val travelDateId: Int,
    val totalAmount: Double = 0.0
)
