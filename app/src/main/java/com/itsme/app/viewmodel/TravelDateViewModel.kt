package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.TravelDate
import kotlinx.coroutines.launch

class TravelDateViewModel(app: Application) : AndroidViewModel(app) {
    private val dao = (app as ItsmeApplication).database.travelDateDao()
    val all = dao.getAll()
    val active = dao.getActive()
    fun insert(td: TravelDate) = viewModelScope.launch { dao.insert(td) }
    fun update(td: TravelDate) = viewModelScope.launch { dao.update(td) }
    fun delete(td: TravelDate) = viewModelScope.launch { dao.delete(td) }
    fun activate(id: Int) = viewModelScope.launch { dao.deactivateAll(); dao.activate(id) }
    fun complete(id: Int) = viewModelScope.launch { dao.complete(id) }
}
