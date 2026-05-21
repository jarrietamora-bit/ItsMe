package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "clients")
data class Client(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val fullName: String,
    val email: String,
    val phone: String
)
