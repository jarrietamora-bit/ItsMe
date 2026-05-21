package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.WishListHeader
import com.itsme.app.data.entities.WishListItem
import kotlinx.coroutines.launch

class WishListViewModel(app: Application) : AndroidViewModel(app) {
    private val dao = (app as ItsmeApplication).database.wishListDao()
    private val _tid = MutableLiveData(0)
    val headers: LiveData<List<WishListHeader>> = _tid.switchMap { if (it > 0) dao.getHeadersByTravel(it) else MutableLiveData(emptyList()) }
    fun setTravel(id: Int) { _tid.value = id }
    fun items(hid: Int) = dao.getItems(hid)
    fun insertHeader(h: WishListHeader) = viewModelScope.launch { dao.insertHeader(h) }
    fun updateHeader(h: WishListHeader) = viewModelScope.launch { dao.updateHeader(h) }
    fun deleteHeader(h: WishListHeader) = viewModelScope.launch { dao.deleteItemsByHeader(h.id); dao.deleteHeader(h) }
    fun insertItem(i: WishListItem) = viewModelScope.launch { dao.insertItem(i) }
    fun updateItem(i: WishListItem) = viewModelScope.launch { dao.updateItem(i) }
    fun deleteItem(i: WishListItem) = viewModelScope.launch { dao.deleteItem(i) }
}
