package com.itsme.app.data.entities
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "travel_dates")
data class TravelDate(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val name: String,
    val startDate: String,
    val endDate: String,
    val isActive: Boolean = false,
    val isCompleted: Boolean = false
)
